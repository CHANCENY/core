<?php

namespace Simp\Core\extends\page_builder\src\Plugin;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\helpers\FileFunction;
use Simp\Core\modules\user\current_user\CurrentUser;

class PdfLink
{
    protected File $file;

    public function __construct(protected Page $page)
    {
        $fid = $this->getPdf();

        if (empty($fid)) {
            $system = new SystemDirectory;
            $baseDir   = $system->private_dir;
            $save_path = 'public://contents/pdfs';
            $temp_path = $baseDir . '/tmp/mpdf';

            if (!is_dir($save_path)) {
                mkdir($save_path, 0777, true);
            }
            if (!is_dir($temp_path)) {
                mkdir($temp_path, 0777, true);
            }

            $pdf_path = sprintf(
                '%s%s%s_%s.pdf',
                $save_path,
                DIRECTORY_SEPARATOR,
                $this->page->getName(),
                $this->page->id()
            );

            try {
                // ✅ Set safe defaults for fonts
                $defaultConfig     = (new ConfigVariables())->getDefaults();
                $fontDirs          = $defaultConfig['fontDir'];
                $defaultFontConfig = (new FontVariables())->getDefaults();
                $fontData          = $defaultFontConfig['fontdata'];

                $mpdf = new Mpdf([
                    'tempDir'      => $temp_path,
                    'fontDir'      => array_merge($fontDirs, [__DIR__ . '/fonts']), // optional custom fonts
                    'fontdata'     => $fontData,
                    'default_font' => 'dejavusans', // ✅ avoid unsupported font error
                ]);

                $css     = $this->page->getCss() ?? '';
                $content = $this->page->getContent() ?? '';

// Write CSS first as HEADER_CSS
                $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// Then write the HTML body
                $mpdf->WriteHTML($content, \Mpdf\HTMLParserMode::HTML_BODY);
                $mpdf->Output($pdf_path, 'F');

                if (file_exists($pdf_path)) {
                    $file = File::create([
                        'uri'       => $pdf_path,
                        'size'      => filesize($pdf_path),
                        'name'      => pathinfo($pdf_path, PATHINFO_FILENAME).".".pathinfo($pdf_path, PATHINFO_EXTENSION),
                        'extension' => pathinfo($pdf_path, PATHINFO_EXTENSION),
                        'mime_type' => mime_content_type($pdf_path),
                        'uid'       => CurrentUser::currentUser()->getUser()->getUid(),
                    ]);

                    if ($file) {
                        $fid      = $file->getFid();
                        $query    = "INSERT INTO page_builder_pdf_links (pid, fid) VALUES (:pid, :fid)";
                        $statement = Database::database()->con()->prepare($query);
                        $statement->bindValue('pid', $this->page->id());
                        $statement->bindValue('fid', $fid);
                        $statement->execute();
                    }
                }
            } catch (\Throwable $e) {
                error_log('PDF generation failed: ' . $e->getMessage()); // ✅ log for debugging
                $fid = 0;
            }
        }

        $this->file = File::load($fid);
    }

    protected function getPdf(): int|null
    {
        $query = "SELECT fid FROM page_builder_pdf_links WHERE pid = :pid";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue('pid', $this->page->id());
        $statement->execute();
        $pdf = $statement->fetch();
        return $pdf['fid'] ?? null;
    }

    public function getUri(): string
    {
        return $this->file?->getUri() ?? '';
    }

    public function getWebLink(): string
    {
        return $this->file ? FileFunction::reserve_uri($this->file->getUri()) : '';
    }

    public function getDownloadLink(): string
    {
        return $this->file ? FileFunction::reserve_uri($this->file->getUri(), true) : '';
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public static function factory(Page $page): PdfLink
    {
        return new PdfLink($page);
    }
}

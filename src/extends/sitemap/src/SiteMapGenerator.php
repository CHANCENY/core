<?php

namespace Simp\Core\extends\sitemap\src;

use Simp\Core\lib\routes\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteMapGenerator
{
    private readonly int $limit;

    private readonly int $page;

    public function __construct(private readonly array $paths, int $limit = 10, int $page = 1)
    {
        $this->limit = max(1, $limit);
        $this->page  = max(1, $page);
    }

    public function generate(Request $request): Response
    {
        $totalPaths = count($this->paths);
        $totalPages = (int) ceil($totalPaths / $this->limit);
        $base_url = $request->getSchemeAndHttpHost();

        // If page is not set or page = 0 → return sitemap index
        if (!$request->query->has('page')) {
            $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            for ($i = 1; $i <= $totalPages; $i++) {
                $url = Route::url('sitemap.xml', [], ['page' => $i]);
                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>" .$base_url . htmlspecialchars((string) $url, ENT_XML1) . "</loc>\n";
                $xml .= "    <lastmod>" . date('c') . "</lastmod>\n";
                $xml .= "  </sitemap>\n";
            }

            $xml .= '</sitemapindex>';

            return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        // otherwise → return the specific page sitemap
        $offset     = ($this->page - 1) * $this->limit;
        $pagedPaths = array_slice($this->paths, $offset, $this->limit);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pagedPaths as $path) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars((string) $path['url'], ENT_XML1) . "</loc>\n";

            if (!empty($path['modified'])) {
                $xml .= "    <lastmod>" .$base_url . htmlspecialchars((string) $path['modified'], ENT_XML1) . "</lastmod>\n";
            }

            if (!empty($path['priority'])) {
                $xml .= "    <priority>" . htmlspecialchars((string)$path['priority'], ENT_XML1) . "</priority>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

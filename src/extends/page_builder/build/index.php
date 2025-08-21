<?php
@session_start();

require_once realpath( "../../../../../vendor/autoload.php");

use Simp\Core\components\request\Request;
use Simp\Core\extends\page_builder\src\Plugin\Page;
use Simp\Core\extends\page_builder\src\Plugin\PageConfigManager;
use Simp\Core\lib\installation\InstallerValidator;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\user\current_user\CurrentUser;

$validator = new InstallerValidator();

$user = CurrentUser::currentUser();

$request = Request::createFromGlobals();

if (!$user->isIsAdmin()) {
   $redirect = new \Symfony\Component\HttpFoundation\RedirectResponse(Route::url('system.error.page.denied'));
   $redirect->setStatusCode(403);
   $redirect->send();
   exit;
}

// Get page data
$page = $request->get('t', null);
$config = PageConfigManager::factory($page ?? "");
$pages = $config->getPages();
$current_published = array_filter($pages, function ($page) {
   return $page->getStatus() == 1;
});

$current_published = !empty($current_published) ? reset($current_published) : new Page(0);

/**@var Page $current_published */
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>GrapesJS Demo - Free Open Source Website Page Builder</title>
    <meta content="Best Free Open Source Responsive Websites Builder" name="description">
    <link rel="stylesheet" href="stylesheets/toastr.min.css">
    <link rel="stylesheet" href="stylesheets/grapes.min.css?v0.16.3">
    <link rel="stylesheet" href="stylesheets/grapesjs-preset-webpage.min.css">
    <link rel="stylesheet" href="stylesheets/tooltip.css">
    <link rel="stylesheet" href="stylesheets/grapesjs-plugin-filestack.css">
    <link rel="stylesheet" href="stylesheets/demos.css?v3">
    <link href="https://unpkg.com/grapick/dist/grapick.min.css" rel="stylesheet">

    <!-- <script src="//static.filestackapi.com/v3/filestack.js"></script> -->
    <!-- <script src="js/aviary.js"></script> old //feather.aviary.com/imaging/v3/editor.js -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="js/toastr.min.js"></script>

    <script src="js/grapes.min.js?v0.16.3"></script>
    <script src="js/grapesjs-preset-webpage.min.js?v0.1.11"></script>
    <script src="js/grapesjs-lory-slider.min.js?0.1.5"></script>
    <script src="js/grapesjs-tabs.min.js?0.1.1"></script>
    <script src="js/grapesjs-custom-code.min.js?0.1.2"></script>
    <script src="js/grapesjs-touch.min.js?0.1.1"></script>
    <script src="js/grapesjs-parser-postcss.min.js?0.1.1"></script>
    <script src="js/grapesjs-tooltip.min.js?0.1.1"></script>
    <script src="js/grapesjs-tui-image-editor.min.js?0.1.2"></script>
    <script src="js/grapesjs-typed.min.js?1.0.5"></script>
    <script src="js/grapesjs-style-bg.min.js?1.0.1"></script>
      <script src="js/grapesjs-plugin-ckeditor.min.js"></script>
    <script src="js/my-ul-block.js"></script>

    <style type="text/css">
        .icon-add-comp::before, .icon-comp100::before,.icon-comp50::before,.icon-comp30::before,.icon-rm::before{ content: '';}
        .icon-add-comp {
          background: url("./img/icon-sq-a.png") no-repeat center;
        }
        .icon-comp100 {
          background: url("./img/icon-sq-1.png") no-repeat center;
        }
        .icon-comp50 {
          background: url("./img/icon-sq-2.png") no-repeat center;
        }
        .icon-comp30 {
          background: url("./img/icon-sq-3.png") no-repeat center;
        }
        .icon-rm {
          background: url("./img/icon-sq-r.png") no-repeat center;
        }

        .icons-flex {
          background-size: 70% 65% !important;
          height: 15px;
          width: 17px;
          opacity: 0.9;
        }
        .icon-dir-row {
          background: url("./img/flex-dir-row.png") no-repeat center;
        }
        .icon-dir-row-rev {
          background: url("./img/flex-dir-row-rev.png") no-repeat center;
        }
        .icon-dir-col {
          background: url("./img/flex-dir-col.png") no-repeat center;
        }
        .icon-dir-col-rev {
          background: url("./img/flex-dir-col-rev.png") no-repeat center;
        }
        .icon-just-start{
         background: url("./img/flex-just-start.png") no-repeat center;
        }
        .icon-just-end{
         background: url("./img/flex-just-end.png") no-repeat center;
        }
        .icon-just-sp-bet{
         background: url("./img/flex-just-sp-bet.png") no-repeat center;
        }
        .icon-just-sp-ar{
         background: url("./img/flex-just-sp-ar.png") no-repeat center;
        }
        .icon-just-sp-cent{
         background: url("./img/flex-just-sp-cent.png") no-repeat center;
        }
        .icon-al-start{
         background: url("./img/flex-al-start.png") no-repeat center;
        }
        .icon-al-end{
         background: url("./img/flex-al-end.png") no-repeat center;
        }
        .icon-al-str{
         background: url("./img/flex-al-str.png") no-repeat center;
        }
        .icon-al-center{
         background: url("./img/flex-al-center.png") no-repeat center;
        }

         [data-tooltip]::after {
           background: rgba(51, 51, 51, 0.9);
         }

         .gjs-pn-commands {
           min-height: 40px;
         }

         #gjs-sm-float,
         .gjs-pn-views .fa-cog {
            display: none;
         }

         .gjs-am-preview-cont {
           height: 100px;
           width: 100%;
         }

         .gjs-logo-version {
           background-color: #756467;
         }

        .gjs-pn-panel.gjs-pn-views {
          padding: 0;
          border-bottom: none;
        }

        .gjs-pn-btn.gjs-pn-active {
          box-shadow: none;
        }

        .gjs-pn-views .gjs-pn-btn {
            margin: 0;
            height: 40px;
            padding: 10px;
            width: 33.3333%;
            border-bottom: 2px solid rgba(0, 0, 0, 0.3);
        }

        .CodeMirror {
          min-height: 450px;
          margin-bottom: 8px;
        }
        .grp-handler-close {
          background-color: transparent;
          color: #ddd;
        }

        .grp-handler-cp-wrap {
          border-color: transparent;
        }
    </style>
  </head>
  <body>
    <div style="display: none">
      <div class="gjs-logo-cont">
        <a href="//grapesjs.com"><img class="gjs-logo" src="img/grapesjs-logo-cl.png"></a>
        <div class="gjs-logo-version"></div>
      </div>
    </div>
    <div class="ad-cont">
      <!-- <script async type="text/javascript" src="//cdn.carbonads.com/carbon.js?zoneid=1673&serve=C6AILKT&placement=grapesjscom" id="_carbonads_js"></script> -->
      <div id="native-carbon"></div>
      <script async type="text/javascript" src="./js/carbon.js?v2"></script>
    </div>

    <div id="gjs" style="height:0px; overflow:hidden">

        <?php if (!empty($current_published->getContent())): ?>
        <?= $current_published->getContent() ?>
        <?php else: ?>
        <section class="flex-sect">
            <div class="container-width">

            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($current_published->getCss())): ?>
            <style>
                <?= $current_published->getCss() ?>
            </style>
        <?php  else: ?>
        <style>
            .container-width{
                width: 90%;
                max-width: 1150px;
                margin: 0 auto;
            }
        </style>
        <?php endif; ?>

    </div>

    <div id="info-panel" style="display:none">
      <br/>
      <svg class="info-panel-logo" xmlns="//www.w3.org/2000/svg" version="1"><g id="gjs-logo">
        <path d="M40 5l-12.9 7.4 -12.9 7.4c-1.4 0.8-2.7 2.3-3.7 3.9 -0.9 1.6-1.5 3.5-1.5 5.1v14.9 14.9c0 1.7 0.6 3.5 1.5 5.1 0.9 1.6 2.2 3.1 3.7 3.9l12.9 7.4 12.9 7.4c1.4 0.8 3.3 1.2 5.2 1.2 1.9 0 3.8-0.4 5.2-1.2l12.9-7.4 12.9-7.4c1.4-0.8 2.7-2.2 3.7-3.9 0.9-1.6 1.5-3.5 1.5-5.1v-14.9 -12.7c0-4.6-3.8-6-6.8-4.2l-28 16.2" style="fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;stroke-width:10;stroke:#fff"/>
      </g></svg>
      <br/>
      <div class="info-panel-label">
        <b>GrapesJS Webpage Builder</b> is a simple showcase of what is possible to achieve with the
        <a class="info-panel-link gjs-four-color" target="_blank" href="https://github.com/artf/grapesjs">GrapesJS</a>
        core library
        <br/><br/>
        For any hint about the demo check the
        <a class="info-panel-link gjs-four-color" target="_blank" href="https://github.com/artf/grapesjs-preset-webpage">Webpage Preset repository</a>
        and open an issue. For problems with the builder itself, open an issue on the main
        <a class="info-panel-link gjs-four-color" target="_blank" href="https://github.com/artf/grapesjs">GrapesJS repository</a>
        <br/><br/>
        Being a free and open source project contributors and supporters are extremely welcome.
        If you like the project support it with a donation of your choice or become a backer/sponsor via
        <a class="info-panel-link gjs-four-color" target="_blank" href="https://opencollective.com/grapesjs">Open Collective</a>
      </div>
    </div>

    <script src="/core/modules/page_builder/assets/builder-script.js"></script>
  </body>
</html>

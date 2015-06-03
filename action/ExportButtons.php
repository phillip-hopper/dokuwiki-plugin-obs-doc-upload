<?php
/**
 * Name: ExportButtons.php
 * Description: A Dokuwiki action plugin to show OBS export buttons.
 *
 * Author: Phil Hopper
 * Date:   2015-05-23
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// $door43shared is a global instance, and can be used by any of the door43 plugins
if (empty($door43shared)) {
    $door43shared = plugin_load('helper', 'door43shared');
}

/* @var $door43shared helper_plugin_door43shared */
$door43shared->loadActionBase();
$door43shared->loadAjaxHelper();

class action_plugin_door43obsdocupload_ExportButtons extends Door43_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller the DokuWiki event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, 'handle_obs_action');
        Door43_Ajax_Helper::register_handler($controller, 'get_obs_doc_export_dlg', array($this, 'get_obs_doc_export_dlg'));
        Door43_Ajax_Helper::register_handler($controller, 'download_obs_template_docx', array($this, 'download_obs_template_docx'));

    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_obs_action(Doku_Event &$event, /** @noinspection PhpUnusedParameterInspection */ $param) {

        if ($event->data !== 'show') return;

        global $INFO;

        $parts = explode(':', strtolower($INFO['id']));

        // If this is an OBS request, the id will have these parts:
        // [0] = language code / namespace
        // [1] = 'obs'
        // [2] = story number '01' - '50'
        if (count($parts) < 2) return;
        if ($parts[1] !== 'obs') return;
        if (isset($parts[2]) && (preg_match('/^[0-9][0-9]$/', $parts[2]) !== 1)) return;

        $html = file_get_contents(dirname(dirname(__FILE__)) . '/templates/obs_export_buttons.html');

        echo $this->translateHtml($html, $this->lang);
    }

    public function get_obs_doc_export_dlg() {

        /* @var $door43shared helper_plugin_door43shared */
        global $door43shared;

        // $door43shared is a global instance, and can be used by any of the door43 plugins
        if (empty($door43shared)) {
            $door43shared = plugin_load('helper', 'door43shared');
        }

        $html = file_get_contents($this->root . '/templates/obs_export_dlg.html');
        if (!$this->localised) $this->setupLocale();
        $html = $door43shared->translateHtml($html, $this->lang);

        echo $html;
    }

    public function download_obs_template_docx() {

        // get the obs data
        global $INPUT;
        $langCode = $INPUT->str('lang');

        $url = "https://api.unfoldingword.org/obs/txt/1/{$langCode}/obs-{$langCode}.json";
        $raw = file_get_contents($url);
        $obs = json_decode($raw, true);
        $markdown = '';

        foreach ($obs['chapters'] as $chapter) {

            $markdown .= $chapter['title'] . "\n";
            $markdown .= str_repeat('=', strlen($chapter['title'])) . "\n\n";

            foreach ($chapter['frames'] as $frame) {
                $markdown .= "{$frame['text']}\n\n";
            }

            $markdown .= "*{$chapter['ref']}*\n\n";
            $markdown .= "-----\n\n";
        }

        // create the temp markdown file
        $increment = 0;
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'obs2docx';

        while (is_dir($tempDir . DIRECTORY_SEPARATOR . $increment)) {
            $increment++;
        }

        $tempDir = $tempDir . DIRECTORY_SEPARATOR . $increment;
        mkdir($tempDir, 0755, true);

        $markdownFile = $tempDir . DIRECTORY_SEPARATOR . 'obs.md';
        $docxFile = $tempDir . DIRECTORY_SEPARATOR . 'obs.docx';
        file_put_contents($markdownFile, $markdown);

        // convert to docx with pandoc
        $cmd = "/usr/bin/pandoc \"$markdownFile\" -s -f markdown -t docx  -o \"$docxFile\"";
        exec($cmd, $output, $error);

        // send to the browser
        if (is_file($docxFile)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8');
            header('Content-Length: ' . filesize($docxFile));
            header('Content-Disposition: attachment; filename="obs_template.docx"');

            readfile($docxFile);
        }
        else {
            if (!$this->localised) $this->setupLocale();
            header('Content-Type: text/plain');
            echo $this->getLang('docxFileCreateError');
        }

        // cleanup
        /* @var $door43shared helper_plugin_door43shared */
        global $door43shared;

        // $door43shared is a global instance, and can be used by any of the door43 plugins
        if (empty($door43shared)) {
            $door43shared = plugin_load('helper', 'door43shared');
        }

        $door43shared->delete_directory_and_files($tempDir);
    }
}

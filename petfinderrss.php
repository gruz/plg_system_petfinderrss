<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Http\Transport\CurlTransport;

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

class plgSystemPetfinderrss extends JPlugin
{
    protected $autoloadLanguage = true;

    protected $cache;

    protected $base_path = 'https://api.petfinder.com/v2/';

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    function onAjaxPetfinderrss()
    {
        $this->checkParams();
        $token = $this->getToken();

        $jinput = Factory::getApplication()->input;
        $endpoint = $jinput->get('entity', 'animals');

        $query = Uri::getInstance()->getQuery();
        // $query = 'organization=CO149&type=dog&tpl=blog&status=adoptable';

        $uri = Uri::getInstance($this->base_path . $endpoint . '?' . $query);

        $options = new Registry();
        $transport = new CurlTransport($options);
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];
        $response = $transport->request('get', $uri, null, $headers);
        $output = json_decode($response->body);

        $doc = Factory::getDocument();
        if (!empty($output->$endpoint)) {
            foreach ($output->$endpoint as $key => $row) {
                $item               = new FeedItem;
                $item->title        = $this->buildTitle($row);
                $item->link         = $row->url;
                $item->date         = $row->status_changed_at ? $row->status_changed_at  : $row->published;
                $item->description  = $this->buildDescription($row);
                $item->category = array();
                $doc->addItem($item);
            }
        }

        $jinput->set('type', 'rss');

        return $doc->render();
    }

    private function buildDescription($row)
    {
        $jinput = Factory::getApplication()->input;
        $tpl = $jinput->get('tpl');

        $description = [];

        $photo = '';
        if (!empty($row->primary_photo_cropped)) {
            $photo = $row->primary_photo_cropped;
        } else {
            if (!empty($row->photos)) {
                $photo = $row->photos[0];
            }
        }

        if (!empty($photo)) {
            $photo = HTMLHelper::image($photo->large, $row->name);
        }

        $templates = $this->params->get('options');
        $found = false;
        foreach ($templates as $template) {
            if($tpl === $template->name) {
                $descr = '';
                eval('$descr = <<<DEMO' . PHP_EOL .$template->value . PHP_EOL . 'DEMO;');
                $description[] = $descr;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $description[] = $row->description;
        }

        $description = implode(PHP_EOL, $description);

        return $description;
    }

    private function buildTitle($row)
    {
        $title = [];

        $title[] = $row->name;
        $title[] = strtolower($row->size);
        $title[] = strtolower($row->gender);
        $title[] = $row->breeds->primary;

        $title = implode(', a ', $title);

        return $title;
    }

    private function checkParams()
    {
        $api_public_key = $this->params->get('api_public_key');
        $api_secret_key = $this->params->get('api_secret_key');

        $error_messages = [];
        if (!$api_public_key || !$api_secret_key) {
            $error_messages[] = Text::_('PLG_SYSTEM_PETFINDERRSS');
            if (!$api_public_key) {
                $error_messages[] = Text::_('Petfinder Public API key not found');
            }
            if (!$api_secret_key) {
                $error_messages[] = Text::_('PLG_SYSTEM_PETFINDERRSS_NOT_FOUND') . ' ' . Text::_('PLG_SYSTEM_PETFINDERRSS_API_SECRET_KEY');
            }
            $error_messages[] = Text::_('PLG_SYSTEM_PETFINDERRSS_API_KEYS_PARAMS_SCREENSHOT');
            throw new Error(implode('<br>', $error_messages), 500);
        }
    }

    private function getToken()
    {
        $getToken = function () {    // This function calculates the biggest 3 tables from the selection below
            $options = new Registry();
            $uri = 'oauth2/token';
            $uri = Uri::getInstance($this->base_path . $uri);
            $transport = new CurlTransport($options);
            $api_public_key = $this->params->get('api_public_key');
            $api_secret_key = $this->params->get('api_secret_key');
            $data = [
                "grant_type" => "client_credentials",
                "client_id" => $api_public_key,
                "client_secret" => $api_secret_key,
            ];
            $response = $transport->request('POST', $uri, $data);
            $output = json_decode($response->body);
            $token = $output->access_token;

            return $token;
        };

        return $getToken();
    }
}

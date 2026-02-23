<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/InternautenB2BImporter.php';

class InternautenB2BImport extends Module
{
    const CONFIG_URL = 'INTERNAUTENB2BIMPORT_URL';
    const CONFIG_GROUP_ID = 'INTERNAUTENB2BIMPORT_GROUP_ID';
    const CONFIG_CRON_TOKEN = 'INTERNAUTENB2BIMPORT_CRON_TOKEN';
    const CONFIG_TIMEOUT = 'INTERNAUTENB2BIMPORT_TIMEOUT';
    const CONFIG_DEBUG = 'INTERNAUTENB2BIMPORT_DEBUG';

    public function __construct()
    {
        $this->name = 'internautenb2bimport';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.4';
        $this->author = 'die.internauten.ch';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Internauten B2B Import');
        $this->description = $this->l('Imports wholesale prices and sets specific prices by product reference.');
    }

    public function install()
    {
        return parent::install() && $this->installConfig();
    }

    public function uninstall()
    {
        return $this->uninstallConfig() && parent::uninstall();
    }

    private function installConfig()
    {
        $defaultToken = Tools::passwdGen(32);

        Configuration::updateValue(self::CONFIG_URL, 'https://your.url.com/api/preisliste');
        Configuration::updateValue(self::CONFIG_GROUP_ID, 5);
        Configuration::updateValue(self::CONFIG_CRON_TOKEN, $defaultToken);
        Configuration::updateValue(self::CONFIG_TIMEOUT, 20);
        Configuration::updateValue(self::CONFIG_DEBUG, 0);

        return true;
    }

    private function uninstallConfig()
    {
        Configuration::deleteByName(self::CONFIG_URL);
        Configuration::deleteByName(self::CONFIG_GROUP_ID);
        Configuration::deleteByName(self::CONFIG_CRON_TOKEN);
        Configuration::deleteByName(self::CONFIG_TIMEOUT);
        Configuration::deleteByName(self::CONFIG_DEBUG);

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInternautenb2bImportConfig')) {
            $this->saveConfig();
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        if (Tools::isSubmit('submitInternautenb2bImportNow')) {
            $report = $this->runImport();
            $output .= $this->renderReport($report);
        }

        $output .= $this->renderForm();
        $output .= $this->renderActionForm();
        $output .= $this->renderCronInfo();

        return $output;
    }

    private function saveConfig()
    {
        Configuration::updateValue(self::CONFIG_URL, Tools::getValue(self::CONFIG_URL));
        Configuration::updateValue(self::CONFIG_GROUP_ID, (int) Tools::getValue(self::CONFIG_GROUP_ID));
        Configuration::updateValue(self::CONFIG_CRON_TOKEN, Tools::getValue(self::CONFIG_CRON_TOKEN));
        Configuration::updateValue(self::CONFIG_TIMEOUT, (int) Tools::getValue(self::CONFIG_TIMEOUT));
        Configuration::updateValue(self::CONFIG_DEBUG, (int) Tools::getValue(self::CONFIG_DEBUG));
    }

    private function runImport()
    {
        $url = Configuration::get(self::CONFIG_URL);
        $groupId = (int) Configuration::get(self::CONFIG_GROUP_ID);
        $timeout = (int) Configuration::get(self::CONFIG_TIMEOUT);
        $debug = (bool) Configuration::get(self::CONFIG_DEBUG);
        $shopId = (int) $this->context->shop->id;

        $importer = new InternautenB2BImporter($this->context);

        return $importer->run($url, $groupId, $shopId, $timeout, $debug);
    }

    private function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitInternautenb2bImportConfig';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = $this->getConfigFormValues();

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Import URL'),
                        'name' => self::CONFIG_URL,
                        'required' => true,
                        'desc' => $this->l('Full URL to the export endpoint.'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Customer group'),
                        'name' => self::CONFIG_GROUP_ID,
                        'required' => true,
                        'options' => array(
                            'query' => $this->getCustomerGroupOptions(),
                            'id' => 'id_group',
                            'name' => 'name',
                        ),
                        'desc' => $this->l('Specific prices apply to this customer group.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Cron token'),
                        'name' => self::CONFIG_CRON_TOKEN,
                        'required' => true,
                        'desc' => $this->l('Required token for the cron endpoint.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('HTTP timeout (seconds)'),
                        'name' => self::CONFIG_TIMEOUT,
                        'required' => true,
                        'desc' => $this->l('Max wait time for the import request.'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable debug logging'),
                        'name' => self::CONFIG_DEBUG,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'debug_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'debug_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                        'desc' => $this->l('Log reference and price for each import row.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        return $helper->generateForm(array($fieldsForm));
    }

    private function renderActionForm()
    {
        $action = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');

        return '<div class="panel">'
            . '<h3>' . $this->l('Run Import') . '</h3>'
            . '<form method="post" action="' . $action . '">'
            . '<button class="btn btn-primary" name="submitInternautenb2bImportNow" value="1" type="submit">'
            . $this->l('Run import now')
            . '</button>'
            . '</form>'
            . '</div>';
    }

    private function renderCronInfo()
    {
        $cronUrl = $this->context->link->getModuleLink(
            $this->name,
            'cron',
            array('token' => Configuration::get(self::CONFIG_CRON_TOKEN)),
            true
        );

        return '<div class="panel">'
            . '<h3>' . $this->l('Cron URL') . '</h3>'
            . '<p>' . $this->l('Call this URL from your scheduler:') . '</p>'
            . '<p><code>' . $cronUrl . '</code></p>'
            . '</div>';
    }

    private function renderReport(array $report)
    {
        if (!empty($report['errors'])) {
            $output = '<div class="alert alert-danger">'
                . $this->l('Import failed:')
                . '<ul>';

            foreach ($report['errors'] as $error) {
                $output .= '<li>' . $error . '</li>';
            }

            return $output . '</ul></div>';
        }

        return '<div class="alert alert-success">'
            . $this->l('Import completed.')
            . '<ul>'
            . '<li>' . $this->l('Created:') . ' ' . (int) $report['created'] . '</li>'
            . '<li>' . $this->l('Updated:') . ' ' . (int) $report['updated'] . '</li>'
            . '<li>' . $this->l('Skipped:') . ' ' . (int) $report['skipped'] . '</li>'
            . '<li>' . $this->l('Deleted:') . ' ' . (int) $report['deleted'] . '</li>'
            . '</ul>'
            . '</div>';
    }

    private function getConfigFormValues()
    {
        return array(
            self::CONFIG_URL => Configuration::get(self::CONFIG_URL),
            self::CONFIG_GROUP_ID => Configuration::get(self::CONFIG_GROUP_ID),
            self::CONFIG_CRON_TOKEN => Configuration::get(self::CONFIG_CRON_TOKEN),
            self::CONFIG_TIMEOUT => Configuration::get(self::CONFIG_TIMEOUT),
            self::CONFIG_DEBUG => (int) Configuration::get(self::CONFIG_DEBUG),
        );
    }

    private function getCustomerGroupOptions()
    {
        $groups = Group::getGroups($this->context->language->id);
        $options = array();

        foreach ($groups as $group) {
            $options[] = array(
                'id_group' => (int) $group['id_group'],
                'name' => $group['name'],
            );
        }

        return $options;
    }
}

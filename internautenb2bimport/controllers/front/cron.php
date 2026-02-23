<?php

class Internautenb2bimportCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $token = Tools::getValue('token');
        $expectedToken = Configuration::get(InternautenB2BImport::CONFIG_CRON_TOKEN);

        if (empty($token) || $token !== $expectedToken) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(array('error' => 'Invalid token.'));
            exit;
        }

        $url = Configuration::get(InternautenB2BImport::CONFIG_URL);
        $groupId = (int) Configuration::get(InternautenB2BImport::CONFIG_GROUP_ID);
        $timeout = (int) Configuration::get(InternautenB2BImport::CONFIG_TIMEOUT);
        $shopId = (int) $this->context->shop->id;

        $importer = new InternautenB2BImporter($this->context);
        $report = $importer->run($url, $groupId, $shopId, $timeout);

        header('Content-Type: application/json');
        echo json_encode($report);
        exit;
    }
}

<?php

class InternautenB2BImporter
{
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function run($url, $groupId, $shopId, $timeout, $debug)
    {
        $report = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'errors' => array(),
        );

        if (empty($url)) {
            $report['errors'][] = 'Missing import URL.';
            return $report;
        }

        if ($groupId <= 0) {
            $report['errors'][] = 'Invalid customer group ID.';
            return $report;
        }

        $raw = $this->fetchData($url, $timeout);
        if ($raw === false) {
            $report['errors'][] = 'Failed to fetch import data.';
            return $report;
        }

        $rows = $this->parseData($raw);
        if (empty($rows)) {
            $report['errors'][] = 'No import rows found.';
            return $report;
        }

        foreach ($rows as $row) {
            $reference = $this->getValue($row, 'nummer');
            $priceRaw = $this->getValue($row, 'preisgrossisten');

            $reference = $this->normalizeReference($reference);

            if (empty($reference)) {
                $report['skipped']++;
                $this->logDebugReference($reference, $priceRaw, 'Empty reference', $debug);
                continue;
            }

            if ($priceRaw === null || $priceRaw === '') {
                if ($this->removeSpecificPrice($reference, $groupId, $shopId)) {
                    $report['deleted']++;
                    $this->logDebugReference($reference, $priceRaw, 'Empty price, deleted', $debug);
                } else {
                    $report['skipped']++;
                    $this->logDebugReference($reference, $priceRaw, 'Empty price, skipped', $debug);
                }
                continue;
            }

            $idProduct = (int) Product::getIdByReference(trim($reference));
            if ($idProduct <= 0) {
                $report['skipped']++;
                $this->logDebugReference($reference, $priceRaw, 'Product not found', $debug);
                continue;
            }

            $price = $this->normalizePrice($priceRaw);
            if ($price <= 0) {
                if ($this->removeSpecificPrice($reference, $groupId, $shopId)) {
                    $report['deleted']++;
                    $this->logDebugReference($reference, $priceRaw, 'Invalid price, deleted', $debug);
                } else {
                    $report['skipped']++;
                    $this->logDebugReference($reference, $priceRaw, 'Invalid price, skipped', $debug);
                }
                continue;
            }

            $result = $this->upsertSpecificPrice($idProduct, $groupId, $shopId, $price);
            if ($result === 'created') {
                $report['created']++;
                $this->logDebugReference($reference, $priceRaw, 'Specific price created', $debug);
            } else {
                $report['updated']++;
                $this->logDebugReference($reference, $priceRaw, 'Specific price updated', $debug);
            }
        }

        return $report;
    }

    private function logDebugReference($reference, $priceRaw, $reason = null, $debug = true)
    {
        if (!$debug) {
            return;
        }

        $message = sprintf(
            'InternautenB2B import reference: %s | PreisGrossisten: %s | Reason: %s',
            $reference !== null ? (string) $reference : 'null',
            $priceRaw !== null ? (string) $priceRaw : 'null',
            $reason !== null ? (string) $reason : 'null'
        );
        $this->writeLogLine($message);
    }

    private function writeLogLine($message)
    {
        $logDir = '/var/log/internautenb2bimport';
        $logFile = $logDir . '/internautenb2bimport.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function normalizeReference($reference)
    {
        if ($reference === null) {
            return null;
        }

        $reference = trim((string) $reference);
        if ($reference === '') {
            return $reference;
        }

        if (is_numeric($reference)) {
            return rtrim(rtrim($reference, '0'), '.');
        }

        return $reference;
    }

    private function fetchData($url, $timeout)
    {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => (int) $timeout,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ),
        ));

        return Tools::file_get_contents($url, false, $context);
    }

    private function parseData($raw)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return array();
        }

        $firstChar = substr(ltrim($raw), 0, 1);
        if ($firstChar === '{' || $firstChar === '[') {
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($data['data']) && is_array($data['data'])) {
                    $data = $data['data'];
                } elseif (isset($data['items']) && is_array($data['items'])) {
                    $data = $data['items'];
                }

                if (is_array($data)) {
                    return array_values($data);
                }
            }
        }

        return $this->parseCsv($raw);
    }

    private function parseCsv($raw)
    {
        $raw = (string) $raw;
        if (trim($raw) === '') {
            return array();
        }

        $delimiter = $this->detectDelimiter($raw);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return array();
        }

        fwrite($handle, $raw);
        rewind($handle);

        $headers = null;
        $rows = array();

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isEmptyCsvRow($values)) {
                continue;
            }

            if ($headers === null) {
                $headers = $values;
                continue;
            }

            $row = array();
            foreach ($headers as $index => $header) {
                $row[$header] = array_key_exists($index, $values) ? $values[$index] : null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter($line)
    {
        return ',';
    }

    private function isEmptyCsvRow(array $values)
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function getValue($row, $key)
    {
        if (!is_array($row)) {
            return null;
        }

        if (array_key_exists($key, $row)) {
            return $row[$key];
        }

        $normalizedKey = $this->normalizeKey($key);
        $normalizedRow = array();

        foreach ($row as $field => $value) {
            $normalizedRow[$this->normalizeKey($field)] = $value;
        }

        return array_key_exists($normalizedKey, $normalizedRow) ? $normalizedRow[$normalizedKey] : null;
    }

    private function normalizeKey($key)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $key));
    }

    private function normalizePrice($value)
    {
        $clean = preg_replace('/[^0-9,\.\-]/', '', (string) $value);

        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    private function removeSpecificPrice($reference, $groupId, $shopId)
    {
        if (empty($reference)) {
            return false;
        }

        $idProduct = (int) Product::getIdByReference(trim((string) $reference));
        if ($idProduct <= 0) {
            return false;
        }

        $idSpecificPrice = $this->findSpecificPriceId($idProduct, $groupId, $shopId);
        if ($idSpecificPrice) {
            $specificPrice = new SpecificPrice((int) $idSpecificPrice);
            $specificPrice->delete();
            return true;
        }
        return false;
    }

    private function upsertSpecificPrice($idProduct, $groupId, $shopId, $price)
    {
        $idSpecificPrice = $this->findSpecificPriceId($idProduct, $groupId, $shopId);
        if ($idSpecificPrice) {
            $specificPrice = new SpecificPrice((int) $idSpecificPrice);
            $action = 'updated';
        } else {
            $specificPrice = new SpecificPrice();
            $action = 'created';
        }

        $specificPrice->id_product = (int) $idProduct;
        $specificPrice->id_product_attribute = 0;
        $specificPrice->id_customer = 0;
        $specificPrice->id_group = (int) $groupId;
        $specificPrice->id_shop = (int) $shopId;
        $specificPrice->id_currency = 0;
        $specificPrice->id_country = 0;
        $specificPrice->price = (float) $price;
        $specificPrice->from_quantity = 1;
        $specificPrice->reduction = 0;
        $specificPrice->reduction_type = 'amount';
        $specificPrice->reduction_tax = 0;
        $specificPrice->from = '0000-00-00 00:00:00';
        $specificPrice->to = '0000-00-00 00:00:00';

        if ($action === 'created') {
            $specificPrice->add();
        } else {
            $specificPrice->update();
        }

        return $action;
    }

    private function findSpecificPriceId($idProduct, $groupId, $shopId)
    {
        $sql = 'SELECT id_specific_price'
            . ' FROM ' . _DB_PREFIX_ . 'specific_price'
            . ' WHERE id_product = ' . (int) $idProduct
            . ' AND id_product_attribute = 0'
            . ' AND id_customer = 0'
            . ' AND id_group = ' . (int) $groupId
            . ' AND id_shop = ' . (int) $shopId
            . ' AND id_currency = 0'
            . ' AND id_country = 0'
            . ' AND from_quantity = 1'
            . ' ORDER BY id_specific_price DESC';

        return (int) Db::getInstance()->getValue($sql);
    }
}

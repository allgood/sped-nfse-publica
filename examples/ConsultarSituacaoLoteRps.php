<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../bootstrap.php';

use NFePHP\Common\Certificate;
use NFePHP\NFSePublica\Tools;
use NFePHP\NFSePublica\Common\Soap\SoapCurl;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $production = ($_ENV['NFSE_PRODUCTION'] == 'true');
    
    $config = [
        'cnpj'  => $_ENV['NFSE_COMPANY_CNPJ'],
        'im'    => $_ENV['NFSE_COMPANY_IM'],
        'cmun'  => $production ? $_ENV['NFSE_COMPANY_IBGE'] : "1234567",
        'razao' => $_ENV['NFSE_COMPANY_NAME'],
        'tpamb' => $production ? 1 : 2,
        'consoledebug' => true,
    ];
    
    $configJson = json_encode($config);
    
    $content = file_get_contents(__DIR__ . '/' . $_ENV['NFSE_CERTIFICATE_FILE']);
    $password = $_ENV['NFSE_CERTIFICATE_PASSWORD'];
    $cert = Certificate::readPfx($content, $password);
    
    $soap = new SoapCurl($cert);
    $soap->disableCertValidation(true);
    
    $tools = new Tools($configJson, $cert);
    $tools->loadSoapClass($soap);
    
    $protocolo = $argv[1];
    
    $response = $tools->consultarSituacaoLoteRps($protocolo);
    var_dump($response);
} catch (\Exception $e) {
    echo $e->getMessage();
}

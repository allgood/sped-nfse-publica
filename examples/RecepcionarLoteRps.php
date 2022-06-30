<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../bootstrap.php';

use NFePHP\Common\Certificate;
use NFePHP\NFSePublica\Tools;
use NFePHP\NFSePublica\Rps;
use NFePHP\NFSePublica\Common\Soap\SoapCurl;
use Carbon\Carbon;

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
    
    $arps = [];
    
    $std = new \stdClass();
    $std->version = '3.01'; //false
    $std->dataemissao = Carbon::now('America/Sao_Paulo')->format("Y-m-d\TH:i:s"); //false
    $std->status = 1;  // true
    $std->regimeespecialtributacao = 1;
    $std->optantesimplesnacional = $production ? 1 : 2;
    $std->incentivadorcultural = 2; // true
    $std->naturezaoperacao = $production ? 11 : 101;
    
    $std->identificacaorps = new \stdClass(); //false
    $std->identificacaorps->numero = date('U');
    $std->identificacaorps->serie = 'A1';
    $std->identificacaorps->tipo = 1;
    
    $std->servico = new \stdClass(); //true
    $std->servico->responsavelretencao = null; //false
    $std->servico->itemlistaservico = '1401'; //true
    $std->servico->codigoTributacaomunicipio = null;
    $std->servico->discriminacao = 'Teste de RPS'; //true
    $std->servico->informacoescomplementares = ''; //true
    $std->servico->codigomunicipio = $_ENV['NFSE_COMPANY_IBGE']; // true
    $std->servico->codigopais = null; //false
    $std->exigibilidadeiss = 1;
    $std->municipioincidencia = $_ENV['NFSE_COMPANY_IBGE'];
    $std->servico->numeroprocesso = null;
    
    $std->servico->valores = new \stdClass(); //true
    $std->servico->valores->issretido = 2; //true
    // $std->servico->valores->valorissretido = 10.00; //false
    // $std->servico->valores->outrasretencoes = 10.00; //false
    $std->servico->valores->valorservicos = 100.00; //true
    // $std->servico->valores->valordeducoes = 10.00; //false
    $std->servico->valores->aliquota = 0.02; //false

    // $std->servico->valores->valorpis = 10.00; //false
    // $std->servico->valores->valorcofins = 10.00; //false
    // $std->servico->valores->valorinss = 10.00; //false
    // $std->servico->valores->valorir = 10.00; //false
    // $std->servico->valores->valorcsll = 10.00; //false
    // $std->servico->valores->valoriss = 10.00; //false
    // $std->servico->valores->basecalculo = 10.00; //false
    // $std->servico->valores->valorliquidonfse = 10.00; //false
    // $std->servico->valores->descontoincondicionado = 10.00; //false
    // $std->servico->valores->descontocondicionado = 10.00; //false
    
    $std->tomador = new \stdClass(); //false
    // $std->tomador->cnpj = null; //false
    $std->tomador->cpf = $_ENV['NFSE_TOMADOR_CPF']; //false
    $std->tomador->razaosocial = "Fulano de Tal"; //false
    // $std->tomador->telefone = '123456789'; //false
    $std->tomador->email = 'fulano@example.com'; //false
    
    $std->tomador->endereco = new \stdClass(); //false
    $std->tomador->endereco->endereco = 'Rua das Rosas'; //false
    $std->tomador->endereco->numero = '111'; //false
    $std->tomador->endereco->complemento = 'Sobre Loja'; //false
    $std->tomador->endereco->bairro = 'Centro'; //false
    $std->tomador->endereco->codigomunicipio = $_ENV['NFSE_COMPANY_IBGE']; //false
    $std->tomador->endereco->uf = $_ENV['NFSE_COMPANY_STATE']; //false
    // $std->tomador->endereco->codigopais = null; //false
    $std->tomador->endereco->cep = $_ENV['NFSE_COMPANY_ZIPCODE']; //false
    
    $arps[] = new Rps($std);
    
    $lote = $std->identificacaorps->numero;
    $response = $tools->recepcionarLoteRps($arps, $lote);  //METODO ASSINCRONO
    
    echo $response;
    echo "\n\n";
    $xmlresponse = simplexml_load_string($response);
    var_dump($xmlresponse);
} catch (\Exception $e) {
    echo $e->getMessage();
}

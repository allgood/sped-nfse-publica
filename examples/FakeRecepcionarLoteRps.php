<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../bootstrap.php';

use NFePHP\Common\Certificate;
use NFePHP\NFSePublica\Tools;
use NFePHP\NFSePublica\Rps;
use NFePHP\NFSePublica\Common\Soap\SoapFake;
use NFePHP\NFSePublica\Common\FakePretty;

try {

    $config = [
        'cnpj'  => '99999999000191',
        'im'    => '1733160024',
        'cmun'  => '4203006', //ira determinar as urls e outros dados
        'razao' => 'Empresa Test Ltda',
        'tpamb' => 2
    ];

    $configJson = json_encode($config);

    $content = file_get_contents('expired_certificate.pfx');
    $password = 'associacao';
    $cert = Certificate::readPfx($content, $password);

    $soap = new SoapFake();
    $soap->disableCertValidation(true);

    $tools = new Tools($configJson, $cert);
    $tools->loadSoapClass($soap);

    $arps = [];

    $std->identificacaorps = new \stdClass(); //false
    $std->identificacaorps->numero = 3;
    $std->identificacaorps->serie = 'A1';
    $std->identificacaorps->tipo = 1;

    $std->servico = new \stdClass(); //true
    $std->servico->responsavelretencao = null; //false
    $std->servico->itemlistaservico = '11.01'; //true
    $std->servico->discriminacao = 'Teste de RPS'; //true
    $std->servico->informacoescomplementares = 'alguma observação complementar'; //true
    $std->servico->codigomunicipio = '3106200'; // true
    $std->servico->codigopais = null; //false

    $std->servico->valores = new \stdClass(); //true
    $std->servico->valores->valorservicos = 100.00; //true
    $std->servico->valores->valordeducoes = 10.00; //false
    $std->servico->valores->valorpis = 10.00; //false
    $std->servico->valores->valorcofins = 10.00; //false
    $std->servico->valores->valorinss = 10.00; //false
    $std->servico->valores->valorir = 10.00; //false
    $std->servico->valores->valorcsll = 10.00; //false
    $std->servico->valores->issretido = 2; //true
    $std->servico->valores->valoriss = 10.00; //false
    $std->servico->valores->valorissretido = 10.00; //false
    $std->servico->valores->outrasretencoes = 10.00; //false
    $std->servico->valores->basecalculo = 10.00; //false
    $std->servico->valores->aliquota = 5; //false
    $std->servico->valores->valorliquidonfse = 10.00; //false
    $std->servico->valores->descontoincondicionado = 10.00; //false
    $std->servico->valores->descontocondicionado = 10.00; //false

    $std->tomador = new \stdClass(); //false
    $std->tomador->cnpj = "99999999000191"; //false
    $std->tomador->cpf = null; //false
    $std->tomador->razaosocial = "Fulano de Tal"; //false
    $std->tomador->telefone = '123456789'; //false
    $std->tomador->email = 'fulano@mail.com'; //false

    $std->tomador->endereco = new \stdClass(); //false
    $std->tomador->endereco->endereco = 'Rua das Rosas'; //false
    $std->tomador->endereco->numero = '111'; //false
    $std->tomador->endereco->complemento = 'Sobre Loja'; //false
    $std->tomador->endereco->bairro = 'Centro'; //false
    $std->tomador->endereco->codigomunicipio = '3106200'; //false
    $std->tomador->endereco->uf = 'MG'; //false
    $std->tomador->endereco->codigopais = null; //false
    $std->tomador->endereco->cep = '30160010'; //false

    $std->intermediarioservico = new \stdClass(); //false
    $std->intermediarioservico->cnpj = '99999999000191'; //false
    $std->intermediarioservico->cpf = null; //false
    $std->intermediarioservico->inscricaomunicipal = '8041700010';
    $std->intermediarioservico->razaosocial = "Beltrano da Silva";

    $arps[] = new Rps($std);

    $lote = '3';
    $response = $tools->recepcionarLoteRps($arps, $lote);  //METODO ASSINCRONO

    echo FakePretty::prettyPrint($response, '');
} catch (\Exception $e) {
    echo $e->getMessage();
}
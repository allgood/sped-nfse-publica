<?php

namespace NFePHP\NFSePublica\Common;

/**
 * Auxiar Tools Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSePublica
 * @copyright NFePHP Copyright (c) 2020
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-publica for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Strings;
use NFePHP\NFSePublica\Common\Soap\SoapCurl;
use NFePHP\NFSePublica\Common\Soap\SoapInterface;

class Tools
{
    public $lastRequest;

    protected $config;
    protected $prestador;
    protected $certificate;
    protected $wsobj;
    protected $soap;
    protected $environment;

    /**
     * Constructor
     * @param string $config
     * @param Certificate $cert
     */
    public function __construct($config, Certificate $cert)
    {
        $this->config = json_decode($config);
        $this->certificate = $cert;
        $this->wsobj = $this->loadWsobj($this->config->cmun);
        $this->environment = 'homologacao';
        if ($this->config->tpamb === 1) {
            $this->environment = 'producao';
        }
        $this->buildPrestadorTag();
    }

    /**
     * Build tag Prestador
     */
    protected function buildPrestadorTag()
    {
        $this->prestador = "<Prestador id=\"prestador\">";
        if (!empty($this->config->cnpj)) {
            $this->prestador .= "<Cnpj>{$this->config->cnpj}</Cnpj>";
        } else {
            $this->prestador .= "<Cpf>{$this->config->cpf}</Cpf>";
        }
        $this->prestador .= "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>"
            . "</Prestador>";
    }

    /**
     * load webservice parameters
     * @param string $cmun
     * @return object
     * @throws \Exception
     */
    protected function loadWsobj($cmun)
    {
        $path = realpath(__DIR__ . "/../../storage/urls_webservices.json");
        $urls = json_decode(file_get_contents($path), true);
        if (empty($urls[$cmun])) {
            throw new \Exception("Não localizado parâmetros para esse municipio.");
        }
        return (object)$urls[$cmun];
    }

    /**
     * SOAP communication dependency injection
     * @param SoapInterface $soap
     */
    public function loadSoapClass(SoapInterface $soap)
    {
        $this->soap = $soap;
    }

    /**
     * Sign XML passing in content
     * @param string $content
     * @param string $tagname
     * @param string $mark
     * @return string XML signed
     */
    public function sign($content, $tagname, $mark)
    {
        $xml = Signer::sign(
            $this->certificate,
            $content,
            $tagname,
            $mark
        );
        return $xml;
    }

    /**
     * Send message to webservice
     * @param string $message
     * @param string $operation
     * @return string XML response from webservice
     */
    public function send($message, $operation)
    {
        $consoledebug = (isset($this->config->consoledebug) && $this->config->consoledebug);

        $action = $operation;
        $url = $this->wsobj->homologacao;
        if ($this->environment === 'producao') {
            $url = $this->wsobj->producao;
        }
        if (empty($url)) {
            throw new \Exception("Não está registrada a URL para o ambiente "
                . "de {$this->environment} desse municipio.");
        }

        if ($consoledebug) {
            echo "\nDebug communication\n";
            echo "Operation: ".$operation."\n";
            echo "URL: ".$url.'\n';
            echo "Message:\n".$message."\n\n";
        }
        
        $request = $this->createSoapRequest($message, $operation);
        
        if ($consoledebug) {
            echo "Soap Request:\n".$request."\n\n";
        }
        
        $this->lastRequest = $request;
        if (empty($this->soap)) {
            $this->soap = new SoapCurl($this->certificate);
        }
        $msgSize = strlen($request);
        $parameters = [
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: \"$action\"",
            "Content-length: $msgSize"
        ];
        $response = (string)$this->soap->send(
            $operation,
            $url,
            $action,
            $request,
            $parameters
        );
        
        if ($consoledebug) {
            echo 'Response: '.$response."\n\n";
        }
        return $this->extractContentFromResponse($response, $operation);
    }

    /**
     * Extract xml response from CDATA outputXML tag
     * @param string $response Return from webservice
     * @return string XML extracted from response
     */
    protected function extractContentFromResponse($response, $operation)
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($response);
        if (!empty($dom->getElementsByTagName('return')->item(0))) {
            $node = $dom->getElementsByTagName('return')->item(0);
            $text = str_replace("ISO-8859-1", "UTF-8", $node->textContent);
            return Strings::normalize($text);
        }
        return $response;
    }

    /**
     * Build SOAP request
     * @param string $message
     * @param string $operation
     * @return string XML SOAP request
     */
    protected function createSoapRequest($message, $operation)
    {
        $cdata = htmlspecialchars($message, ENT_NOQUOTES);
        $env = "<soapenv:Envelope "
            . "xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" "
            . "xmlns:e=\"{$this->wsobj->soapns}\">"
            . "<soapenv:Body>"
            . "<e:{$operation}>"
            . "<XML>{$cdata}</XML>"
            . "</e:{$operation}>"
            . "</soapenv:Body>"
            . "</soapenv:Envelope>";

        return $env;
    }
}

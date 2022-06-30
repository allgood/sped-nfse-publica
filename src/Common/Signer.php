<?php

namespace NFePHP\NFSePublica\Common;

/**
 * Class for signing XML in Nacional Standard NFSe
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
use NFePHP\Common\Validator;
use NFePHP\Common\Certificate\PublicKey;
use NFePHP\Common\Exception\SignerException;
use DOMDocument;
use DOMNode;
use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class Signer
{
    const CANONICAL = [true, false, null, null];

    /**
     * Make Signature tag
     * @param Certificate $certificate
     * @param string $content xml for signed
     * @param string $tagname
     * @param string $mark for URI (opcional)
     * @param int $algorithm (opcional)
     * @param array $canonical parameters to format node for signature (opcional)
     * @param string $rootname name of tag to insert signature block (opcional)
     * @return string
     * @throws SignerException
     */
    public static function sign(
        Certificate $certificate,
        $content,
        $tagname,
        $mark = 'Id',
        $algorithm = OPENSSL_ALGO_SHA1,
        $canonical = self::CANONICAL,
        $rootname = ''
    ) {
    
        if (empty($content)) {
            throw SignerException::isNotXml();
        }
        if (!Validator::isXML($content)) {
            throw SignerException::isNotXml();
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($content);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $root = $dom->documentElement;
        if (!empty($rootname)) {
            $root = $dom->getElementsByTagName($rootname)->item(0);
        }
        
        if ($tagname !== null) {
            $node = $dom->getElementsByTagName($tagname)->item(0);
            if (empty($node) || empty($root)) {
                throw SignerException::tagNotFound($tagname);
            }
        } else {
            $node = null;
        }
        $dom = self::createSignature(
            $certificate,
            $dom,
            $root,
            $node,
            $mark,
            $algorithm,
            $canonical
        );
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Method that provides the signature of xml as standard SEFAZ
     * @param Certificate $certificate
     * @param \DOMDocument $dom
     * @param \DOMNode $root xml root
     * @param \DOMElement $node node to be signed
     * @param string $mark Marker signed attribute
     * @param int $algorithm cryptographic algorithm (opcional)
     * @param array $canonical parameters to format node for signature (opcional)
     * @return \DOMDocument
     */
    private static function createSignature(
        Certificate $certificate,
        DOMDocument $dom,
        DOMNode $root,
        ?DOMElement $node,
        $mark,
        $algorithm = OPENSSL_ALGO_SHA1,
        $canonical = self::CANONICAL
    ) {

        /* */
        // Create a new Security object
        $objDSig = new XMLSecurityDSig();
        // Use the c14n exclusive canonicalization
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        // Sign using SHA-1
        $objDSig->addReference(
            $node ? $node : $dom,
            XMLSecurityDSig::SHA1,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
                // [ 'http://www.w3.org/TR/1999/REC-xpath-19991116' => [
                //    'query' => 'not(ancestor-or-self::ds:Signature)'
                // ]]
            ],
            [ 'force_uri' => true , 'id_name' => $mark , 'overwrite' => false ]
        );
        
        // Create a new (private) Security key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        // Load the private key
        $objKey->loadKey($certificate->privateKey);
        
        // Sign the XML file
        $objDSig->sign($objKey);
        
        // Add the associated public key to the signature
        $objDSig->add509Cert($certificate->publicKey);
        
        // Append the signature to the XML
        $objDSig->appendSignature($root);
        // Save the signed XML

        return $dom;
    }

    /**
     * Remove old signature from document to replace it
     * @param string $content
     * @return string
     */
    public static function removeSignature($content)
    {
        if (!self::existsSignature($content)) {
            return $content;
        }
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($content);
        $node = $dom->documentElement;
        $signature = $node->getElementsByTagName('Signature')->item(0);
        if (!empty($signature)) {
            $parent = $signature->parentNode;
            $parent->removeChild($signature);
        }
        return $dom->saveXML();
    }

    /**
     * Verify if xml signature is valid
     * @param string $content
     * @param string $tagname tag for sign (opcional)
     * @param array $canonical parameters to format node for signature (opcional)
     * @return boolean
     * @throws SignerException Not is a XML, Digest or Signature dont match
     */
    public static function isSigned($content, $tagname = '', $canonical = self::CANONICAL)
    {
        if (!self::existsSignature($content)) {
            return false;
        }
        if (!self::digestCheck($content, $tagname, $canonical)) {
            return false;
        }
        return self::signatureCheck($content, $canonical);
    }

    /**
     * Check if Signature tag already exists
     * @param string $content
     * @return boolean
     */
    public static function existsSignature($content)
    {
        if (!Validator::isXML($content)) {
            throw SignerException::isNotXml();
        }
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($content);
        $signature = $dom->getElementsByTagName('Signature')->item(0);
        return !empty($signature);
    }

    /**
     * Verify signature value from SignatureInfo node and public key
     * @param string $xml
     * @param array $canonical
     * @return boolean
     */
    public static function signatureCheck($xml, $canonical = self::CANONICAL)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $signature = $dom->getElementsByTagName('Signature')->item(0);
        $sigMethAlgo = $signature->getElementsByTagName('SignatureMethod')->item(0)->getAttribute('Algorithm');
        $algorithm = OPENSSL_ALGO_SHA256;
        if ($sigMethAlgo == 'http://www.w3.org/2000/09/xmldsig#rsa-sha1') {
            $algorithm = OPENSSL_ALGO_SHA1;
        }
        $certificateContent = $signature->getElementsByTagName('X509Certificate')->item(0)->nodeValue;
        $publicKey = PublicKey::createFromContent($certificateContent);
        $signInfoNode = self::canonize($signature->getElementsByTagName('SignedInfo')->item(0), $canonical);
        $signatureValue = $signature->getElementsByTagName('SignatureValue')->item(0)->nodeValue;
        $decodedSignature = base64_decode(str_replace(array("\r", "\n"), '', $signatureValue));
        if (!$publicKey->verify($signInfoNode, $decodedSignature, $algorithm)) {
            throw SignerException::signatureComparisonFailed();
        }
        return true;
    }

    /**
     * Verify digest value of data node
     * @param string $xml
     * @param string $tagname
     * @param array $canonical
     * @return bool
     * @throws SignerException
     */
    public static function digestCheck($xml, $tagname = '', $canonical = self::CANONICAL)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $root = $dom->documentElement;
        $signature = $dom->getElementsByTagName('Signature')->item(0);
        $sigURI = $signature->getElementsByTagName('Reference')->item(0)->getAttribute('URI');
        if (empty($tagname)) {
            if (empty($sigURI)) {
                $tagname = $root->nodeName;
            } else {
                $xpath = new \DOMXPath($dom);
                $entries = $xpath->query('//@Id');
                foreach ($entries as $entry) {
                    $tagname = $entry->ownerElement->nodeName;
                    break;
                }
            }
        }
        $node = $dom->getElementsByTagName($tagname)->item(0);
        if (empty($node)) {
            throw SignerException::tagNotFound($tagname);
        }
        $sigMethAlgo = $signature->getElementsByTagName('SignatureMethod')->item(0)->getAttribute('Algorithm');
        $algorithm = 'sha256';
        if ($sigMethAlgo == 'http://www.w3.org/2000/09/xmldsig#rsa-sha1') {
            $algorithm = 'sha1';
        }
        if ($sigURI == '') {
            $node->removeChild($signature);
        }
        $calculatedDigest = self::makeDigest($node, $algorithm, $canonical);
        $informedDigest = $signature->getElementsByTagName('DigestValue')->item(0)->nodeValue;
        if ($calculatedDigest != $informedDigest) {
            throw SignerException::digestComparisonFailed();
        }
        return true;
    }

    /**
     * Calculate digest value for given node
     * @param DOMNode $node
     * @param string $algorithm
     * @param array $canonical
     * @return string
     */
    private static function makeDigest(DOMNode $node, $algorithm, $canonical = self::CANONICAL)
    {
        //calcular o hash dos dados
        $c14n = self::canonize($node, $canonical);
        $hashValue = hash($algorithm, $c14n, true);
        return base64_encode($hashValue);
    }

    /**
     * Reduced to the canonical form
     * @param DOMNode $node
     * @param array $canonical
     * @return string
     */
    private static function canonize(DOMNode $node, $canonical = self::CANONICAL)
    {
        return $node->C14N(
            $canonical[0],
            $canonical[1],
            $canonical[2],
            $canonical[3]
        );
    }
}

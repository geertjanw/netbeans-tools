<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

namespace Application\Pp;

use Applicaton\Entity\PluginVersion;

class Catalog {
    const CATALOG_FILE_NAME = 'catalog.xml';
    const CATALOG_FILE_NAME_EXPERIMENTAL = 'catalog-experimental.xml';

    const REQ_ATTRS_MODULE_codenamebase = 'codenamebase';
    const REQ_ATTRS_MODULE_distribution = 'distribution';
    const REQ_ATTRS_MODULE_downloadsize = 'downloadsize';
    
    const REQ_ATTRS_MANIFEST_OpenIDE_Module = 'OpenIDE-Module';
    const REQ_ATTRS_MANIFEST_OpenIDE_Module_Name = 'OpenIDE-Module-Name';
    const REQ_ATTRS_MANIFEST_OpenIDE_Module_Specification_Version = 'OpenIDE-Module-Specification-Version';

    const REQ_ATTRS_MESSAGEDIGEST_algorithm = 'algorithm';
    const REQ_ATTRS_MESSAGEDIGEST_value = 'value';

    private $_items;
    private $_version;
    private $_isExperimental;
    private $_downloadPath;

    public function __construct($version, $items, $isExperimental = false, $dtdPath, $downloadPath) {
        $this->_version = $version;     
        $this->_items = $items;     
        $this->_isExperimental = $isExperimental;
        $this->_dtdPath = $dtdPath;
        $this->_downloadPath = $downloadPath;
    }

    public function asXml($valiadte = true) {
        $implementation = new \DOMImplementation();
        $dtd = $implementation->createDocumentType('module_updates',
                                    '-//NetBeans//DTD Autoupdate Catalog 2.8//EN',
                                    $this->_dtdPath);

        $xml = $implementation->createDocument('', '', $dtd);
        //$xml = new \DomDocument('1.0', 'UTF-8');       
        $modulesEl = $xml->createElement('module_updates');
        $d = new \DateTime('now');
        $modulesEl->setAttribute('timestamp', $d->format('s/i/h/d/m/Y'));
        
        $licenses = array();

        foreach ($this->_items as $item) {
            $moduleElement = $xml->createElement('module');
            $moduleElement->setAttribute(self::REQ_ATTRS_MODULE_codenamebase, $item->getPlugin()->getArtifactId());
            $moduleElement->setAttribute(self::REQ_ATTRS_MODULE_distribution, $this->_downloadPath.$item->getId());
            $moduleElement->setAttribute(self::REQ_ATTRS_MODULE_downloadsize, '1024');
            $moduleElement->setAttribute('targetcluster', 'nbms');            
            $moduleElement->setAttribute('moduleauthor', $item->getPlugin()->getAuthor()->getEmail());
            
            $manifestElement =$xml->createElement('manifest');  
            $manifestElement->setAttribute(self::REQ_ATTRS_MANIFEST_OpenIDE_Module, $item->getPlugin()->getArtifactId());            
            $manifestElement->setAttribute(self::REQ_ATTRS_MANIFEST_OpenIDE_Module_Name, $item->getPlugin()->getName());            
            $manifestElement->setAttribute(self::REQ_ATTRS_MANIFEST_OpenIDE_Module_Specification_Version, $item->getVersion());            
            $manifestElement->setAttribute('OpenIDE-Module-Long-Description', $item->getPlugin()->getDescription());            

            $moduleElement->appendChild($manifestElement);

            foreach($item->getDigests() as $digest) {
                $messageDigest = $xml->createElement('message_digest');
                $messageDigest->setAttribute(self::REQ_ATTRS_MESSAGEDIGEST_algorithm, $digest->getAlgorithm());
                $messageDigest->setAttribute(self::REQ_ATTRS_MESSAGEDIGEST_value, $digest->getValue());
                $moduleElement->appendChild($messageDigest);
            }

            $modulesEl->appendChild($moduleElement);
        }
        
        $xml->appendChild($modulesEl);
        if ($valiadte) {
            libxml_use_internal_errors(true);
            if (!$xml->validate()) {
                $msg = [];
                foreach (libxml_get_errors() as $error) {
                    array_push($msg, $error->message);
                }
                libxml_clear_errors();
                throw new \Exception('Catalog for '.$this->_version.' is not valid:<br/>'.implode('<br/>', $msg)); 
            }
            libxml_use_internal_errors(false);
        }    
        $xml->formatOutput = TRUE;
        return $xml->saveXML();
    }

    public function storeXml($destinationFolder, $xml) {
        $filename = $this->_isExperimental ? self::CATALOG_FILE_NAME_EXPERIMENTAL : self::CATALOG_FILE_NAME;
        $path = $destinationFolder.'/'.$this->_version.'/'.$filename;
        if(!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        if (!file_put_contents($path, $xml)) {
            throw new \Exception('Unable to save catalog on path '.$path); 
        }
        // save also .gz
        $gz =gzopen($path.'.gz', 'w9');
        gzwrite($gz, $xml);
        gzclose($gz);
        return true;       
    }

}

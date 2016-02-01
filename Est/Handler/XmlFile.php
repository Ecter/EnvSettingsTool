<?php

/**
 * Replace a value in a xml file by an xpath expression
 *
 * Parameters
 * - param1: file
 * - param2: xpath
 * - param3: not used
 *
 * @author Fabrizio Branca
 * @since 2012-10-01
 */
class Est_Handler_XmlFile extends Est_Handler_Abstract {

    /**
     * Apply
     *
     * @throws Exception
     * @return bool
     */
    protected function _apply() {

        // let's use some speaking variable names... :)
        $file = $this->param1;
        $expression = $this->param2;

        if (!is_file($file)) {
            throw new Exception(sprintf('File "%s" does not exist', $file));
        }
        if (!is_writable($file)) {
            throw new Exception(sprintf('File "%s" is not writeable', $file));
        }
        if (empty($expression)) {
            throw new Exception('No xpath defined');
        }
        if (!empty($this->param3)) {
            throw new Exception('Param3 is not used in this handler and must be empty');
        }

        // read file
        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            throw new Exception(sprintf('Error while reading file "%s"', $file));
        }


        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($fileContent);

        $xpath = new DOMXPath($dom);
        $elements = $xpath->query($expression);

        if (!($elements instanceof DOMNodeList)) {
            throw new Exception(sprintf('Error while reading elements by xpath "%s"', $expression));
        }

        if ($elements->length == 0) {
            $elements = $this->_handleNonExistentNodes($expression, $xpath, $dom);
        }

        $changes = 0;
        foreach ($elements as $element) { /* @var $element DOMNode */
            if ($element->nodeValue == $this->value) {
                $this->addMessage(new Est_Message(sprintf('Value "%s" is already in place. Skipping.', $this->value), Est_Message::SKIPPED));
            } else {
                $this->addMessage(new Est_Message(sprintf('Updated value from "%s" to "%s"', $element->nodeValue, $this->value)));
                $element->nodeValue = $this->value;
                $changes++;
            }
        }

        if ($changes > 0) {
            $xmlout = $dom->saveXML($dom, LIBXML_NOEMPTYTAG);

            // Add XML header when present before (DOMDocument::saveXML will remove it)
            if (strncasecmp($fileContent, '<?xml', 5)) {
                $xmlout = preg_replace('~<\?xml[^>]*>\s*~sm', '', $xmlout);
            }

            $res = file_put_contents($file, $xmlout);
            if ($res === false) {
                throw new Exception(sprintf('Error while writing file "%s"', $file));
            }
            $this->setStatus(Est_Handler_Interface::STATUS_DONE);
        } else {
            $this->setStatus(Est_Handler_Interface::STATUS_ALREADYINPLACE);
        }

        return true;
    }

    /**
     * Adds non-existent nodes if possible.
     * Returns array with leaf node.
     *
     * @param string $expression
     * @param DOMXPath $xpath
     * @param DOMDocument $dom
     * @return array
     * @throws Exception
     */
    protected function _handleNonExistentNodes($expression, $xpath, $dom)
    {
        $expressionParts = explode('/', $expression);
        $poppedExpressionParts = array();
        do {
            $poppedExpressionParts[] = array_pop($expressionParts);
            $shortenExpression = join('/', $expressionParts);
            $elements = $xpath->query($shortenExpression);
        } while ($elements->length === 0);
        if ($elements->length === 1) {
            $node = $elements->item(0);
            $newNodeNames = array_reverse($poppedExpressionParts);
            foreach ($newNodeNames as $newNodeName) {
                $newNode = $dom->createElement($newNodeName);
                $node->appendChild($newNode);
                $node = $newNode;
            }
            $elements = array($node);
            return $elements;
        } else {
            $this->setStatus(Est_Handler_Interface::STATUS_ERROR);
            throw new Exception(sprintf('Unable to save value fpr "%s" Xpath', $expression));
        }
    }

}
<?php

namespace ACDH\FCSSRU;

// idea from http://stackoverflow.com/questions/746238/indentation-with-domdocument-in-php

class IndentDomDocument extends \DomDocument {
    protected $whiteSpace = "\t";
    
    public function getWhiteSpaceForIndentation() {
        return $this->whiteSpace;
    }
    
    public function setWhiteSpaceForIndentation($string) {
        $this->whiteSpace = $string;
        return $this;
    }
    
    public function xmlIndent() {
        // Retrieve all text nodes using XPath
        $x = new \DOMXPath($this);
        $nodeList = $x->query("//text()");
        foreach($nodeList as $node) {
            // 1. "Trim" each text node by removing its leading and trailing spaces and newlines.
            $node->nodeValue = preg_replace("/^[\s\r\n]+/", "", $node->nodeValue);
            $node->nodeValue = preg_replace("/[\s\r\n]+$/", "", $node->nodeValue);
            // 2. Resulting text node may have become "empty" (zero length nodeValue) after trim. If so, remove it from the dom.
            if(strlen($node->nodeValue) == 0) $node->parentNode->removeChild($node);
        }
        // 3. Starting from root (documentElement), recursively indent each node. 
        $this->xmlIndentRecursive($this->documentElement, 0);
    } // end function xmlIndent

    private function xmlIndentRecursive($currentNode, $depth) {
        $indentCurrent = true;
        if(($currentNode->nodeType == XML_TEXT_NODE) && ($currentNode->parentNode->childNodes->length == 1)) {
            // A text node being the unique child of its parent will not be indented.
            // In this special case, we must tell the parent node not to indent its closing tag.
            $indentCurrent = false;
        }
        if($indentCurrent && $depth > 0) {
            // Indenting a node consists of inserting before it a new text node
            // containing a newline followed by a number of tabs corresponding
            // to the node depth.
            $textNode = $this->createTextNode("\n" . str_repeat($this->whiteSpace, $depth));
            $currentNode->parentNode->insertBefore($textNode, $currentNode);
        }
        if($currentNode->childNodes) {
            $indentClosingTag = false;
            foreach($currentNode->childNodes as $childNode) $indentClosingTag = $this->xmlIndentRecursive($childNode, $depth+1);
            if($indentClosingTag) {
                // If children have been indented, then the closing tag
                // of the current node must also be indented.
                $textNode = $this->createTextNode("\n" . str_repeat("$this->whiteSpace", $depth));
                $currentNode->appendChild($textNode);
            }
        }
        return $indentCurrent;
    } // end function xmlIndentRecursive

} // end class indentDomDocument

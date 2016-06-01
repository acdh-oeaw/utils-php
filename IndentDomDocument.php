<?php

/* 
 * The MIT License
 *
 * Copyright 2016 OEAW/ACDH.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ACDH\FCSSRU;

\mb_internal_encoding('UTF-8');
\mb_http_output('UTF-8');
\mb_http_input('UTF-8');
\mb_regex_encoding('UTF-8');

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
        $nodeList = $x->query("//text()[not(ancestor-or-self::*/@xml:space = 'preserve')]");
        foreach($nodeList as $node) {
            // 1. "Trim" each text node by removing its leading and trailing spaces and newlines.
            $node->nodeValue = preg_replace("/^[\s\r\n]+/", "", $node->nodeValue);
            $node->nodeValue = preg_replace("/[\s\r\n]+$/", "", $node->nodeValue);
            // 2. Resulting text node may have become "empty" (zero length nodeValue) after trim. If so, remove it from the dom.
            if(mb_strlen($node->nodeValue) == 0) { $node->parentNode->removeChild($node); }
        }
        // 3. Starting from root (documentElement), recursively indent each node. 
        $this->xmlIndentRecursive($this->documentElement, 0);
    } // end function xmlIndent

    /**
     * @param \DomElement $currentNode
     * @param int $depth
     * @return boolean
     */
    private function xmlIndentRecursive($currentNode, $depth) {
        $indentCurrent = true;
        if (!is_object($currentNode)) {
            return false;
        }
        if(($currentNode instanceof \DOMElement) && ($currentNode->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'space') === 'preserve')) {
            $indentCurrent = true;
            return $indentCurrent;
        }
        if(($currentNode->nodeType == XML_TEXT_NODE) && ($currentNode->parentNode->childNodes->length === 1)) {
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
            foreach($currentNode->childNodes as $childNode) { $indentClosingTag = $this->xmlIndentRecursive($childNode, $depth+1); }
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

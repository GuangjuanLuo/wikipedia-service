<?php
/**
 * Thinkmoat (c) 2020 guangjuan
 * API
 *
 * Summary:         WikiPediaAPI
 * Purpose:
 * @Developer:		Frank Frisby
 * @Developed Date:	02/10/2020
 * @Revised by:		Frank Frisby
 * @Revised Date: 	03/15/2020
 *
 ***********************************************************************************
 * CHANGE LOG
 * Date         Developer/Engineer          Description of Change
 * ----------   ------------------          ----------------------------------------
 *
 ***********************************************************************************/

namespace API;

use DOMDocument;
use DOMXPath;


class WikiPediaAPI
{
    public function GetWikipediaFirstParagraph($text) {
        $data = $this->AttemptWikiExtraction($text);
        $paragraphs = $this->SplitTextIntoParagraphs($data);

        foreach($paragraphs as $index => $paragraph) {
            $WordCount = count($this->SplitTextIntoWordList($paragraph));

            if ($WordCount > 10) {
                return $paragraph; 
            }
        }

        return "";
    }

    public function GetWikipediaParagraphs($text, $SelectedParagraphIndices = []): array {
        $ChooseParagraphs = false;
        if (count($SelectedParagraphIndices) > 0) {
            $ChooseParagraphs = true;
        }

        $data = $this->AttemptWikiExtraction($text);

        $paragraphs = $this->SplitTextIntoParagraphs($data);

        if ($ChooseParagraphs) {
            $List = [];
            foreach($SelectedParagraphIndices as $index) {
                $List[] = $paragraphs[$index];
            }

            $paragraphs = $List;
        }

        return $paragraphs;
    }

    private function AttemptWikiExtraction($text) {
        $text = strtolower($text);

        $data = $this->WikiPediaExtract($text);
        
        if ($data == null || $data == "") {
            $text = ucwords($text);
            $data = $this->WikiPediaExtract($text);
        } 
        
        return $data;
    }

    public function WikiPediaExtract($name) {
        $name = $this->ConvertTextToWikiText($name);

        $url = "https://en.wikipedia.org/wiki/$name";

        $html = file_get_contents($url);

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);

        $Bday = $dom->saveXML($xpath->query('//span[@class="bday"]')->item(0));

        $main = $xpath->query('//div[@class="mw-parser-output"]');
        $mainNode = $main->item(0);
        $refinedHTML = $dom->saveHTML($mainNode);

        $dom2 = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom2->loadHTML($refinedHTML);
        libxml_use_internal_errors(false);

        $xpath2 = new DOMXPath($dom2);

        $nonAllowedTags = [ "table", "h1", "h2", "h3", "h4", "img", "ul", "ol" ];
        $nonAllowClasses = [ "shortdescription", "toc", "reflist", "infobox", "div-col", "thumb", "metadata", "tright", "hatnote", "noprint", "contentSub", "plainlinks" ];

        foreach($nonAllowClasses as $className) {
            $Nodes = $xpath2->query("//*[contains(@class, '" . $className. "')]");
            for ($i = $Nodes->length; $i-- > 0;) {
                $node = $Nodes->item($i);
                $node->parentNode->removeChild($node);
            }
        }

        $className = "plainlinks";
        $Nodes = $xpath2->query("//*[contains(@class, '" . $className. "')]");
        for ($i = $Nodes->length; $i-- > 0;) {
            $node = $Nodes->item($i);
            $parent = $node->parentNode;
            $parent->parentNode->removeChild($parent);
        }

        foreach($nonAllowedTags as $tag) {
            $Nodes = $dom2->getElementsByTagName($tag);
            for ($i = $Nodes->length; $i-- > 0;) {
                $node = $Nodes->item($i);
                $node->parentNode->removeChild($node);
            }
        }

        $data = $dom2->saveXML();
        return $this->WikiScrub($data);
    }

    private function ConvertTextToWikiText($name) {
        return str_replace([" ", "  ", "   ", "    ", "     ", "       " ], "_", $name);
    }

    private function WikiScrub($data) {
        $tags = [ "a", "ul", "div" ];

        foreach($tags as $tag) {
            $data = strip_tags($data, $tag);
        }

        $data = urldecode($data);

        // Remove text between Parenthesis
        $data = preg_replace("/\([^)]+\)/", "", $data);

        // Remove text between brackets
        $data = preg_replace("/\{[^}]+\}/", "", $data);

        // Remove text between square brackets
        $data = preg_replace("/\[[^]]+\]/", "", $data);

        $data = str_replace(['[', ']', '{', '}', '(', ')', '<', '>', "\\", "/", '|'], "", $data);

        $data = trim(str_replace('"', "'", $data));
        $data = trim(str_replace("'.", ".", $data));
        $data = trim(str_replace("',", ",", $data));
        $data = trim(str_replace(" '", " ", $data));
        $data = trim(str_replace(". '", ". ", $data));

        $mapping = [
            "&nbsp;" => " ",
            "\\" => "",
            "&#xC2;" => "",
            "&#xB" => "",
            "W&#xEF;" => "",
        ];

        foreach($mapping as $Existing => $Replace) {
            $data = trim(str_replace($Existing, $Replace, $data));
        }

        return $data;
    }

    /**
     * Summary of SplitTextIntoParagraphs
     * ---
     * This method takes a body of text such as an articles and converts it into
     * a list of paragraphs.
     *
     * @param string $Text
     * @return string[]
     */
    private function SplitTextIntoParagraphs($Text) {
        $Text = str_replace(["\n", "\n\n", "\n\n\n", "n\n\n\n", "\n\n\n\n\n", "\n\n\n\n\n\n" ], "\n", $Text);
        $array = explode("\n", $Text);

        foreach($array as $key => $para) {
            if ($para == "") {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Summary of SplitTextIntoWordList
     * ---
     * This method takes a body of text such as paragraphs and converts it into
     * a list of sentences.
     *
     * @param string $Text
     * @return string[]
     */
    private function SplitTextIntoWordList($Text) {
        $SentenceList = preg_split('/(\.\.\.\s?|[-.?!,;:(){}\[\]\"]\s?)|\s/', 
        $Text, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

        return $SentenceList;
    }

}

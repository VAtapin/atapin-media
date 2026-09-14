<?php
namespace App\Services;
class RichContent
{
    public function sanitize(string $html): string
    {
        $document=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        try{$document->loadHTML('<?xml encoding="UTF-8"><div id="editor-body">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
            $root=$document->getElementById('editor-body');if(!$root)return '';
            $clean=function(\DOMNode $node)use(&$clean){
                foreach(iterator_to_array($node->childNodes) as $child){
                    if($child instanceof \DOMComment){$node->removeChild($child);continue;}
                    if(!$child instanceof \DOMElement)continue;
                    $tag=strtolower($child->tagName);
                    if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','textarea','select','link','meta','img','video','audio'],true)){$node->removeChild($child);continue;}
                    $clean($child);
                    if(!in_array($tag,['p','h2','h3','ul','ol','li','blockquote','strong','b','em','i','a','br','pre','code','div'],true)){
                        while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;
                    }
                    $href=$child->getAttribute('href');foreach(iterator_to_array($child->attributes) as $attribute)$child->removeAttributeNode($attribute);
                    if($tag==='a'){
                        $safe=preg_match('~^https?://[^\s]+$~iD',$href)||preg_match('~^/(?!/)[^\s\\\\]*$~D',$href);
                        if($safe){$child->setAttribute('href',$href);$child->setAttribute('rel','noopener noreferrer');}
                    }
                }
            };$clean($root);$result='';foreach($root->childNodes as $child)$result.=$document->saveHTML($child);return trim($result);
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }
}

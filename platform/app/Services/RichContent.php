<?php
namespace App\Services;
class RichContent
{
    private const ALLOWED_TAGS = [
        'p','h1','h2','h3','h4','ul','ol','li','blockquote','strong','b','em','i','u','s','strike','del',
        'a','br','pre','code','div','span','hr','table','thead','tbody','tfoot','tr','th','td','colgroup','col',
    ];

    public function sanitize(?string $html): string
    {
        if($html===null||trim($html)==='')return '';
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
                    if(!in_array($tag,self::ALLOWED_TAGS,true)){
                        while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;
                    }
                    $href=$child->getAttribute('href');$style=$child->getAttribute('style');$colspan=$child->getAttribute('colspan');$rowspan=$child->getAttribute('rowspan');$scope=$child->getAttribute('scope');
                    foreach(iterator_to_array($child->attributes) as $attribute)$child->removeAttributeNode($attribute);
                    if($tag==='a'){
                        $safe=preg_match('~^https?://[^\s]+$~iD',$href)||preg_match('~^mailto:[^\s@]+@[^\s@]+$~iD',$href)||preg_match('~^/(?!/)[^\s\\\\]*$~D',$href);
                        if($safe){$child->setAttribute('href',$href);$child->setAttribute('rel','noopener noreferrer');}
                    }
                    if(in_array($tag,['p','h1','h2','h3','h4','div','span','th','td'],true)&&preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i',$style,$match))$child->setAttribute('style','text-align: '.strtolower($match[1]).';');
                    if(in_array($tag,['th','td'],true)){
                        if(ctype_digit($colspan)&&(int)$colspan>=1&&(int)$colspan<=20)$child->setAttribute('colspan',$colspan);
                        if(ctype_digit($rowspan)&&(int)$rowspan>=1&&(int)$rowspan<=100)$child->setAttribute('rowspan',$rowspan);
                    }
                    if($tag==='th'&&in_array(strtolower($scope),['row','col','rowgroup','colgroup'],true))$child->setAttribute('scope',strtolower($scope));
                }
            };$clean($root);$result='';foreach($root->childNodes as $child)$result.=$document->saveHTML($child);return trim($result);
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }

    public function render(?string $content): string
    {
        $content=(string)$content;if(trim($content)==='')return '';
        if($content===strip_tags($content))return nl2br(e($content),false);
        return $this->sanitize($content);
    }
}

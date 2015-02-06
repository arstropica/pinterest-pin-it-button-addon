<?php
    /*
    Plugin Name: Pinterest "Pin It" Button Addon
    Plugin URI: http://arstropica.com
    Description: Addon for Pinterest "Pin It" Button plugin to place the "Pin It" button above each image in a blog post.
    Author: ArsTropica
    Author URI: http://arstropica.com
    Version: 1.0
    License: GPLv2
    */  

    class pib_addon{
        function pib_addon(){
            add_filter( 'the_content', array(&$this, 'pib_addon_filter_image'), 0);
        }

        function pib_addon_filter_image($content){
            global $post, $wp_query;
            if (is_single() && ($wp_query->post == $post) && (function_exists('pib_button_shortcode_html'))){
                $tagsArry1 = $this->pib_addon_extract_tags($content, array('a'), null, true);
                $tagsArry2 = $this->pib_addon_extract_tags($content, array('img'), true, true);
                $tagsArry3 = $this->pib_addon_extract_tags($content, array('caption'), null, true, 'ISO-8859-1', true);
                $tagsArry = array_merge($tagsArry1, $tagsArry2, $tagsArry3);

                foreach ($tagsArry as $index => $tagArry){
                    if($tagArry['tag_name'] == 'caption'){
                        $search = $tagArry['contents'];
                        $imgArry = $this->pib_addon_extract_tags($search, 'img', null, true);
                        if ( ! empty($search) && ( ! empty($imgArry))){
                            $tagsArry[$index]['has_img'] = true;
                            $dup_keys = $this->pib_addon_asr($imgArry[0]['full_tag'], $tagsArry);
                            if ( ! empty($dup_keys)){
                                foreach($dup_keys as $dup_key => $context){
                                    if ($tagsArry[$dup_key]['tag_name'] == 'img'){
                                        $tagsArry[$dup_key]['nested'] = true;
                                        $tagsArry[$index]['has_img'] = $dup_key;
                                    }
                                }
                            }
                        } 
                    }
                    if (empty($tagsArry[$index]['has_img'])) $tagsArry[$index]['has_img'] = false;
                }

                foreach ($tagsArry as $index => $tagArry){
                    if($tagArry['tag_name'] == 'a'){
                        $search = $tagArry['contents'];
                        $imgArry = $this->pib_addon_extract_tags($search, 'img', null, true);
                        if ( ! empty($search) && ( ! empty($imgArry))){
                            $dup_keys = $this->pib_addon_asr($imgArry[0]['full_tag'], $tagsArry);
                            if ( ! empty($dup_keys)){
                                foreach($dup_keys as $dup_key => $context){
                                    if ($tagsArry[$dup_key]['tag_name'] == 'img' && empty($tagsArry[$dup_key]['nested'])){
                                        $tagsArry[$dup_key]['nested'] = true;
                                        $tagsArry[$index]['has_img'] = $dup_key;
                                    }
                                }
                            }
                        }
                    }
                    if (empty($tagsArry[$index]['has_img'])) $tagsArry[$index]['has_img'] = false;
                }

                foreach ($tagsArry as $index => $tagArry){
                    if($tagArry['tag_name'] == 'img'){
                        if ( ! empty($tagArry['nested'])){
                            $tagsArry[$index]['has_img'] = false;
                        } else {
                            $tagsArry[$index]['has_img'] = $index;
                            $tagsArry[$index]['nested'] = false;
                        }
                    }
                    if (empty($tagsArry[$index]['has_img'])) $tagsArry[$index]['has_img'] = false;
                }

                foreach ($tagsArry as $index => $tagArry){
                    if(( ! empty($tagArry['has_img']))){
                        $search = $tagArry['full_tag'];
                        $imgindex = $tagArry['has_img'];
                        $tag = ($tagArry['tag_name'] == 'caption') ? 'div' : 'span';
                        $style = $tagsArry[$imgindex]['attributes']['style'];
                        $class = 'pinit-wrap ' . $tagsArry[$imgindex]['attributes']['class'] . ($tagArry['tag_name'] == 'caption' ? ' ' . $tagArry['attributes']['align'] : '');
                        $imgid = $this->get_attachment_id_from_class($tagsArry[$imgindex]['attributes']['class']);
                        $imgsrc_arry = wp_get_attachment_image_src($imgid, 'full', false);
                        $imgsrc = ( ! empty($imgsrc_arry)) ? $imgsrc_arry[0] : $tagsArry[$imgindex]['attributes']['src'];
                        $pinit_code = '[pinit count="horizontal" url="' . get_permalink() . '" description="' . get_the_title() . '" image_url="' . $imgsrc . '" remove_div="true"]';
                        $content = str_replace($search, sprintf("<%s style=\"" . $style . "\" class=\"" . $class . "\">%s<br style=\"height: 10px;\"/>%s</%s>", $tag, $pinit_code, $search, $tag), $content);
                    }
                }
                $content = '<style type="text/css">/* <![CDATA[ */ .pinit-wrap IFRAME{margin-bottom: 5px;}/* ]]> */</style>' . "\n" . $content;
            }
            return $content;
        }

        function pib_addon_extract_tags( $html, $tag, $selfclosing = null, $return_the_entire_tag = false, $charset = 'ISO-8859-1', $shortcode=false ){

            if ( is_array($tag) ){
                $tag = implode('|', $tag);
            }

            //If the user didn't specify if $tag is a self-closing tag we try to auto-detect it
            //by checking against a list of known self-closing tags.
            $selfclosing_tags = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta', 'col', 'param' );
            if ( is_null($selfclosing) ){
                $selfclosing = in_array( $tag, $selfclosing_tags );
            }

            //The regexp is different for normal and self-closing tags because I can't figure out
            //how to make a sufficiently robust unified one.
            if ( $selfclosing ){
                $tag_pattern =
                '@<(?P<tag>'.$tag.')           # <tag
                (?P<attributes>\s[^>]+)?       # attributes, if any
                \s*/?>                   # /> or just >, being lenient here
                @xsi';
            } elseif ( $shortcode ) {
                $tag_pattern =
                '@\[(?P<tag>'.$tag.')           # [tag
                (?P<attributes>\s[^\]]+)?       # attributes, if any
                \s*\]                           # ]
                (?P<contents>.*?)         # tag contents
                \[/(?P=tag)\]               # the closing [/tag]
                @xsi';
            } else {
                $tag_pattern =
                '@<(?P<tag>'.$tag.')           # <tag
                (?P<attributes>\s[^>]+)?       # attributes, if any
                \s*>                 # >
                (?P<contents>.*?)         # tag contents
                </(?P=tag)>               # the closing </tag>
                @xsi';
            }

            $attribute_pattern =
            '@
            (?P<name>\w+)                         # attribute name
            \s*=\s*
            (
            (?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
            |                           # or
            (?P<value_unquoted>[^\s"\']+?)(?:\s+|$)           # an unquoted value (terminated by whitespace or EOF)
            )
            @xsi';

            //Find all tags
            if ( !preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ){
                //Return an empty array if we didn't find anything
                return array();
            }

            $tags = array();
            foreach ($matches as $match){

                //Parse tag attributes, if any
                $attributes = array();
                if ( !empty($match['attributes'][0]) ){ 

                    if ( preg_match_all( $attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER ) ){
                        //Turn the attribute data into a name->value array
                        foreach($attribute_data as $attr){
                            if( !empty($attr['value_quoted']) ){
                                $value = $attr['value_quoted'];
                            } else if( !empty($attr['value_unquoted']) ){
                                    $value = $attr['value_unquoted'];
                                } else {
                                    $value = '';
                            }

                            //Passing the value through html_entity_decode is handy when you want
                            //to extract link URLs or something like that. You might want to remove
                            //or modify this call if it doesn't fit your situation.
                            $value = html_entity_decode( $value, ENT_QUOTES, $charset );

                            $attributes[$attr['name']] = $value;
                        }
                    }

                }

                $tag = array(
                'tag_name' => $match['tag'][0],
                'offset' => $match[0][1],
                'contents' => !empty($match['contents'])?$match['contents'][0]:'', //empty for self-closing tags
                'attributes' => $attributes,
                );
                if ( $return_the_entire_tag ){
                    $tag['full_tag'] = $match[0][0];
                }

                $tags[] = $tag;
            }

            return $tags;
        }

        function pib_addon_asr($Needle,$Haystack,$NeedleKey="", $Strict=false,$Path=array(), $top=true) {

            if(!is_array($Haystack))
                return false;
            foreach($Haystack as $Key => $Val) {
                if(is_array($Val)&& $SubPath=$this->pib_addon_asr($Needle,$Val,$NeedleKey, $Strict,array(), false)) {
                    if ($SubPath){
                        if ($top) {
                            $Path[$Key] = $SubPath;
                        } else {
                            $Path = $SubPath;
                        }
                        if (! $top) return $Path;
                    }
                } elseif((!$Strict&&$Val==$Needle&& $Key==(strlen($NeedleKey)>0?$NeedleKey:$Key))|| ($Strict&&$Val===$Needle&&$Key==(strlen($NeedleKey)>0?$NeedleKey:$Key))) {
                    if ($top) {
                        $Path[]=$Key;
                    } else {
                        $Path=$Key;
                    }
                    if (! $top) return $Path;
                }
            }
            if ($top) return $Path;
            else return false;
        }

        function get_attachment_id_from_src ($image_src) {
            global $wpdb;
            $query = "SELECT ID FROM {$wpdb->posts} WHERE guid='$image_src'";
            $id = $wpdb->get_var($query);
            return $id;

        }            

        function get_attachment_id_from_class ($image_class) {
            if (preg_match('/\bwp-image-(\d*)\b/i', $image_class, $matches)){
                $id = $matches[1];
                return $id;
            }
            return false;
        }            
    }

    add_action('init', 'init_pib_addon');
    function init_pib_addon(){
        $pib_addon = new pib_addon();
    }

    register_activation_hook( __FILE__, 'pib_addon_dependentplugins' );
    function pib_addon_dependentplugins(){
        require_once( trailingslashit(ABSPATH) . '/wp-admin/includes/plugin.php' );
        if ( is_plugin_active( 'pinterest-pin-it-button/pinterest-pin-it-button.php' ) ) {
            require_once(trailingslashit(WP_PLUGIN_DIR) . 'pinterest-pin-it-button/pinterest-pin-it-button.php');
        } else {
            deactivate_plugins( __FILE__);
            exit ('Addon for Pinterest "Pin It" Button requires the Pinterest "Pin It" Button plugin to function.');
        }
    }
?>

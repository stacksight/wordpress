<?php

class WPHealthSeo
{
    private $points = 0;
    private $posts = 0;


    public function getSeoValues()
    {
        global $wpdb;
        $sql = "SELECT p.id, p.post_title, pm.meta_value, pm.meta_key
        FROM ch_posts p
        LEFT JOIN ch_postmeta pm ON (pm.post_id = p.ID AND (pm.meta_key = '%s' OR pm.meta_key = '%s'))
        WHERE p.post_type = '%s' AND p.post_status = '%s'
        GROUP BY p.ID
        ORDER BY p.post_date DESC";

        $posts = $wpdb->get_results(
            $wpdb->prepare($sql, WPSEO_Meta::$meta_prefix.'linkdex', WPSEO_Meta::$meta_prefix.'meta-robots-noindex', 'post', 'publish')
        );

        if(!$posts)
            return false;

        $results = array();
        $results['good'] = array();
        $results['ok'] = array();
        $results['bad'] = array();
        $results['no_focus'] = array();
        $results['no_index'] = array();
        foreach($posts as $post){
            if(isset($post->meta_key) && $post->meta_key){
                switch ($post->meta_key){
                    case  WPSEO_Meta::$meta_prefix.'linkdex':
                        if(!isset($post->meta_value) || empty($post->meta_value) || ($post->meta_value > 0 && $post->meta_value < 41)){
                            $results['bad'][] = $this->_addElement($post);
                        } elseif($post->meta_value >= 41 && $post->meta_value < 71){
                            $results['ok'][] = $this->_addElement($post);
                        } elseif($post->meta_value >= 71){
                            $results['good'][] = $this->_addElement($post);
                        }
                        break;
                    case WPSEO_Meta::$meta_prefix.'meta-robots-noindex':
                        $results['no_index'][] = $this->_addElement($post);
                        $results['no_focus'][] = $this->_addElement($post);
                        break;
                }
            } else{
                $results['no_focus'][] = $this->_addElement($post);
            }
        }

        $return['detail'] = $results;
        $return['graphic'] = $this->_graphic_information($results);
        $return['performance'] = $this->_callSEOPerformance();

        return $return;
    }

    private function _graphic_information($data){
        $result = array();
        foreach($data as $key => $item){
            if(!isset($result[$key]))
                $result[$key] = array();
            $result[$key]['count'] = sizeof($item);
            $result[$key]['percent'] = ($result[$key]['count'] / $this->posts) * 100;
        }
        return $result;
    }

    private function _callSEOPerformance(){
        $result = array();
        $result['max_score'] = $this->posts * 100;
        $result['total_score'] = $this->points;
        $result['performance_percent'] = ($result['max_score'] !== 0) ? ($result['total_score'] / $result['max_score']) * 100 : 0;
        return $result;
    }

    private function _addElement($object){
        if($object->meta_key ==  WPSEO_Meta::$meta_prefix.'linkdex')
            $this->points += $object->meta_value;

        $this->posts++;

        $result = array();
        $result['id'] = $object->id;
        $result['title'] = (isset($object->post_title) && !empty($object->post_title)) ? $object->post_title : 'No title';
        $result['link'] = get_permalink($object->id);
        $result['points'] = (isset($object->meta_value)) ? $object->meta_value : 0;
        return $result;
    }
}
<?php 

/**
	 * Plugin Name: Twitch Game Streams
	 * Description: List the most popular live streams of a particular eSport game or search on Twitch.tv, with current viewer count and live previews.
	 * Version: 0.1.1
	 * Author: Snoost
	 * Author URI: https://www.snoost.com/
	 * License: GPL2
 */

function snoost_twitch_streams_load() { register_widget('snoost_twitch_streams'); }
add_action('widgets_init', 'snoost_twitch_streams_load');

class snoost_twitch_streams extends WP_Widget {

  function __construct() {
    parent::__construct(
      'snoost_twitch_streams',
      __('Twitch live streams', 'snoost_twitch_streams_widget_title'), 
      ['description' => __('List the most popular live streams of a particular eSport game or search on Twitch.tv, with current viewer count and live previews.', 'snoost_twitch_streams_title')]
    );
  }
  private function loadVariables($instance) {
    if(isset($instance['title'])) { $instance['title'] = $instance['title']; }
    else { $instance['title'] = __('Live streams', 'snoost_twitch_streams_title'); }

    if(isset($instance['q'])) { $instance['q'] = $instance['q']; }
    else { $instance['q'] = __('Starcraft 2', 'snoost_twitch_streams_q'); }

    if(isset($instance['width'])) { $instance['width'] = $instance['width']; }
    else { $instance['width'] = __(320, 'snoost_twitch_streams_width'); }

    if(isset($instance['height'])) { $instance['height'] = $instance['height']; }
    else { $instance['height'] = __(180, 'snoost_twitch_streams_height'); }

    if(isset($instance['count'])) { $instance['count'] = $instance['count']; }
    else { $instance['count'] = __(5, 'snoost_twitch_streams_count'); }

    if(isset($instance['showpreview'])) { $instance['showpreview'] = $instance['showpreview']; }
    else { $instance['showpreview'] = __(1, 'snoost_twitch_streams_showpreview '); }

    return $instance;
  }

  private function encodeString($str) {
    $str = str_replace([' ', '%', '&', ':', ';', '=', '"', "'"], false, $str);
    $str = base64_encode($str);
    return $str;
  }

  private function getStreams($q) {
    $cacheName = 'snoost_streams_'.$this->encodeString($q);

    $streams = get_option($cacheName);
    $streams = json_decode($streams, true);

    if(json_last_error() == JSON_ERROR_NONE) {
      if($streams['lastUpdated'] > (time()-60*5)) { return $streams['streams']; }
    }

    $streams = wp_remote_retrieve_body(wp_remote_get('https://www.snoost.com/api?q='.urlencode($q), ['timeout' => '5', 'redirection' => 2]));
    $streams = json_decode($streams, true);
    if(json_last_error() != JSON_ERROR_NONE) { return $streams['streams']; }

    $streams = ['lastUpdated' => time(), 'streams' => $streams];
    update_option($cacheName, json_encode($streams));
    return $streams['streams'];
  }

  public function widget($args, $instance) {
    $instance = $this->loadVariables($instance);
    apply_filters('widget_title', $instance['title']);

    $streams = $this->getStreams($instance['q']);

    echo $args['before_widget'];
    echo $args['before_title'].$instance['title'].$args['after_title'];
    echo '<ul>'; $i = 0;
    if(!isset($streams['streams']) or !is_array($streams['streams']) or count($streams['streams']) < 1) {
      echo '<li><a href=""><small><i>No streams are currently live.</i></small></a></li>';
    } else {
      foreach($streams['streams'] as $s) {
        if($s['stream_type'] != 'live') { continue; }

        $i++;
        if($i > $instance['count']) { break; }
        $preview = str_replace(['{width}', '{height}'], [$instance['width'], $instance['height']], $s['preview']['template']);
        echo '<li class="page-item cat-item"><a href="'.$s['channel']['url'].'" target="_blank">'.$s['channel']['display_name'].'<small> ('.$s['viewers'].')</small>'.($instance['showpreview'] == 1 ? '<br /><img src="'.$preview.'">' : '').'</a></li>';
      }
    }
    echo '</ul>';

    echo $args['after_widget'];
  }

  public function form($instance) {
    $instance = $this->loadVariables($instance);

    echo '
<p>Show the most popular live streams for a particular game (or search) on Twitch.tv</p>
<p>
  <label for="'.$this->get_field_id('title').'">Title:</label>
  <input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($instance['title']).'" />
</p>
<p>
  <label for="'.$this->get_field_id('q').'">Search:</label>
  <br /><small style="line-height: 15px !important; color: #777777;">You will get streams based on the search string. E.g. to get live streams for League of Legends, simply type "League of Legends". Streams are sorted by current live viewers.</small>
  <input class="widefat" id="'.$this->get_field_id('q').'" name="'.$this->get_field_name('q').'" type="text" placeholder="Example: Starcraft 2" value="'.esc_attr($instance['q']).'" />
</p>
<p>
  <label for="'.$this->get_field_id('showpreview').'">Show previews:</label>
  <input id="'.$this->get_field_id('showpreview').'" name="'.$this->get_field_name('showpreview').'" type="checkbox" value="1"'.($instance['showpreview'] == 1 ? ' checked' : '').' />
</p>
<p>
  <label>Preview size:</label>
  w<input style="width: 40px;" id="'.$this->get_field_id('width').'" name="'.$this->get_field_name('width').'" type="text" value="'.esc_attr($instance['width']).'" placeholder="Width" /> x 
  h<input style="width: 40px;" id="'.$this->get_field_id('height').'" name="'.$this->get_field_name('height').'" type="text" value="'.esc_attr($instance['height']).'" placeholder="Height" />
</p>
<p>
  <label for="'.$this->get_field_id('count').'">Streams to show (max 10):</label>
  <input style="width: 40px;" id="'.$this->get_field_id('count').'" name="'.$this->get_field_name('count').'" type="text" value="'.esc_attr($instance['count']).'" />
</p>';
  }

  public function update($new_instance, $old_instance) {
    $instance = [];
    $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
    $instance['q'] = (!empty($new_instance['q'])) ? strip_tags($new_instance['q']) : '';
    $instance['showpreview'] = (!empty($new_instance['showpreview'])) ? strip_tags($new_instance['showpreview']) : '';
    $instance['width'] = (!empty($new_instance['width'])) ? strip_tags($new_instance['width']) : '';
    $instance['height'] = (!empty($new_instance['height'])) ? strip_tags($new_instance['height']) : '';
    $instance['count'] = (!empty($new_instance['count'])) ? strip_tags($new_instance['count']) : '';

    return $instance;
  }

}

?>
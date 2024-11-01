<?php
/*
 * Plugin Name: Workbox Nasdaq XML News Reader Plugin.
 * Author: Workbox Inc.
 * Author URI: http://www.workbox.com/
 * Plugin URI: http://blog.workbox.com/wordpress-video-gallery-plugin/
 * Version: 1.0
 * Description: allows to place NASDAQ and custom news feeds onto your WordPress site pages.
 * == Copyright ==
 * Copyright 2008-2016 Workbox Inc (email: support@workbox.com)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

class workboxNasdaqXMLReader {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// activation hook
		register_activation_hook ( __FILE__, array (
				$this,
				'activate'
		) );
		
		// deactivation hook
		register_deactivation_hook ( __FILE__, array (
				$this,
				'deactivate'
		) );
	
		// add options page
		add_action ( 'admin_menu', array (
				$this,
				'setOptionsMenu'
		) );
	
		// do some admin actions
		add_action ( 'admin_init', array (
				$this,
				'adminInit'
		), 10, 0 );
	
		// register a new custom post type 
		add_action('init', array($this, 'setNewPostTypes'));
		
		// import data from Nasdaq 
		add_action('init', array($this, 'refreshData'));
		
		// do some actions after post save action
		add_action('save_post', array($this, 'savePost'));
		
		// add new columns
		add_filter ( 'manage_edit-wb_news_xml_columns', array (
				$this,
				'setCustomColumns'
		) );
		add_action ( 'manage_wb_news_xml_posts_custom_column', array (
				$this,
				'customColumn'
		), 10, 2 );
		
		add_action('admin_head', array($this, 'customAdminCss'));
		
		// widget creation
		add_action('widgets_init',
				create_function('', 'return register_widget("workboxXMLNewsWidget");')
		);
		
		add_shortcode( 'workbox_xml_news',  array($this, 'showShortcode'));
		
		add_filter('post_type_link', array($this, 'correctPermalink'), 10, 4);
	}
	
	/**
	 * Activation
	 */
	public function activate() {
		add_option ( "wb_xml_news_user_id", '', '', 'yes' );
		add_option ( "wb_xml_news_items_amount", 2, '', 'yes' );
		add_option ( "wb_xml_news_cache_minutes", 15, '', 'yes' );
		add_option ( "wb_xml_news_last_cache_time", 0, '', 'yes' );
	
		$this->setNewPostTypes();
		flush_rewrite_rules();
	}
	
	/**
	 * Deactivation
	 */
	public function deactivate() {
	
	}
	
	/**
	 * Add options menu
	 */
	public function setOptionsMenu() {
		add_submenu_page( 'edit.php?post_type=wb_news_xml', 'Options', 'Options', 'manage_options', 'wb_news_xml', array($this, 'showOptionsPage'));
	}
	
	/**
	 * Shows options page
	 */
	public function showOptionsPage() {
		?>
			<h1>News XML Feed Options</h1>
			<?php 
			if (isset($_GET['wbmessage'])) {
				echo '<div id="message" class="updated" style="margin-left: 0"><p>'.urldecode($_GET['wbmessage']).'</p></div><br>';
			}
			?>
			<form name="options_form" method="post">
				<input type="hidden" name="_action" value="WorkboxNewsXMLoptionsUpdate2">
				<table border="0">
					<tr>
						<td align="right">XML Reader User ID:</td>
						<td><input type="text" name="wb_xml_news_user_id" value="<?php echo get_option('wb_xml_news_user_id','') ?>" size="50"></td>
					</tr>
					<tr>
					    <td align="right"></b>News to show (by default)</b>:</td>
					    <td>
				                <input type="text" name="wb_xml_news_items_amount" value="<?php echo get_option('wb_xml_news_items_amount','') ?>" size="5">
					    </td>
					</tr>
				    <tr>
					    <td align="right"></b>Caching Period (in minutes)</b>:</td>
					    <td>
				                <input type="text" name="wb_xml_news_cache_minutes" value="<?php echo get_option('wb_xml_news_cache_minutes','') ?>" size="50">
					    </td>
					</tr>
					<tr>
					    <td align="right"></b>"Read all news" URL</b>:</td>
					    <td>
				                <input type="text" name="wb_xml_news_read_all" value="<?php echo get_option('wb_xml_news_read_all','') ?>" size="50">
					    </td>
					</tr>
					<tr>
						<td>&nbsp;</td>			
						<td><input type="submit" class="button button-primary" value="Update Options"></td>			
					</tr>
				</table>
			</form>
			<br><br>
			<h2>How to use?</h2>
			<ol style="list-style-type: lower-alpha">
				<li><b>Use Workbox XML News Widget.</b> The widget has 2 settings - header and the number of news items to show. If the number is set to 0, the default value will be used (set in plugin options).</li>
				<li><b>You can also utilize shortcodes.</b> Insert shortcode [workbox_xml_news count=XXX] into any page or post. The count parameter indicated the number of news items to show. If this parameter equals 0 or is not indicated, then the default value will be used.</li>
				<li><b>You can also use the plugin inside other plugins or themes.</b> To do this, you can use 2 static methods:
					<br><br>
					<ol style="list-style-type: lower-roman;">
						<li>workboxNasdaqXMLReader::getContent($news_to_show) - returns the list of news. If news_to_show is missing or equals 0, the default value will be used.</li> 
						<li>workboxNasdaqXMLReader::showContent($news_to_show) - returns html code for showing the news items. If news_to_show is missing or equals 0, the default value will be used.</li>
					</ol>
				</li>
			</ol>
			
			<?php
			
			if (is_admin()) {
				?>
				<br><hr><br>
				<h2>Administration options</h2>
				<h3><small>Note! Use with caution!</small></h3>
				<b>News count: </b><?php 
					$info = wp_count_posts('wb_news_xml');
					echo $info->publish;
				?><br><br>
				<a href="edit.php?post_type=wb_news_xml&page=wb_news_xml&_action=WorkboxNewsXMLoptionsClearAll" onclick="return confirm('Arew you sure?')" class="button">Clear all news</a>
				<?php 
			}
		}
		
		/**
		 * Saves options
		 */
		public function adminInit() {
			if (isset($_GET['_action']) && $_GET['_action'] == 'WorkboxNewsXMLoptionsClearAll') {
				// clear all news
				set_time_limit(0);
				$list = get_posts(array('post_type'=>'wb_news_xml', 'nopaging'=>true));
				foreach ($list as $p) {
					if (get_post($p->ID)) {
						wp_delete_post($p->ID);	
					}
				}
				
				update_option('wb_xml_news_last_ts', 0);
				
				wp_redirect ( 'edit.php?post_type=wb_news_xml&page=wb_news_xml&wbmessage=All+posts+deleted!' );
				die ();
			}
			
			if (isset($_POST['_action']) && $_POST['_action'] == 'WorkboxNewsXMLoptionsUpdate2') {
				update_option ( 'wb_xml_news_user_id', (isset($_POST['wb_xml_news_user_id'])?$_POST['wb_xml_news_user_id']:'') );
				update_option ( 'wb_xml_news_items_amount', (isset($_POST['wb_xml_news_items_amount'])?$_POST['wb_xml_news_items_amount']:'')  );
				update_option ( 'wb_xml_news_cache_minutes', (isset($_POST['wb_xml_news_cache_minutes'])?$_POST['wb_xml_news_cache_minutes']:''));
				update_option ( 'wb_xml_news_read_all', (isset($_POST['wb_xml_news_read_all'])?$_POST['wb_xml_news_read_all']:'')  );
			
				wp_redirect ( 'edit.php?post_type=wb_news_xml&page=wb_news_xml&wbmessage=Updated' );
				die ();
			}
			
		}
		
		public function setNewPostTypes() {
			$args = array(
					'labels'=> array(
							'name' => 'NASDAQ XML news feed',
							'singular_name'=>'News Item',
							'add_new'=>'Create new Item',
							'add_new_item'=>'Add new Item',
							'edit_item'=>'Edit Item',
							'new_item'=>'New Item',
							'view_item'=> 'View Item',
							'search_items' => 'Search News',
							'not_found' => 'No News found',
							'not_found_in_trash' => 'No News found in trash',
							'parent_item_colon' => 'Parent News',
							'all_items' => 'All News',
							'archives' => 'News Archives',
							'insert_into_item' => 'Insert into Item',
							'uploaded_to_this_item' => 'Uploaded into this Item',
							'menu_name' => 'NASDAQ XML news feed',
					),
					'public'=>false,
					'exclude_from_search'=>false,
					'show_ui'=>true,
					'register_meta_box_cb'=>array($this, 'registerMetaBox'),
					'supports'=>array('title','editor', 'excerpt', 'custom-fields')
			);
			
			register_post_type('wb_news_xml', $args);
		}
		
		/**
		 * Register metabox
		 */
		public function registerMetaBox() {
			add_meta_box ( __CLASS__ . '_meta', 'Additional Options', array (
					$this,
					'showMetaBox'
			), 'wb_news_xml', 'normal', 'high' );
		}
		
		/**
		 * Shows meta-box with additional fields
		 * @param unknown $post
		 */
		public function showMetaBox($post) {
			?>
			<div>
				<label>External URL:</label>
				<input type="text" style="width: 100%" name="wb_external_url" value="<?php echo esc_attr(get_post_meta($post->ID, 'external_url', true))?>">
			</div>
			<?php 
		}
		
		/**
		 * Save additional fields
		 * @param unknown $post_id
		 */
		public function savePost($post_id) {
			if ( wp_is_post_revision( $post_id ) )
				return;
			
			$data = get_post($post_id);
			
			if (isset($data->ID) && $data->post_type == 'wb_news_xml') {
				update_post_meta($post_id, 'external_url', isset($_POST['wb_external_url'])?$_POST['wb_external_url']:'');
			}
		}
		
		/**
		 * Creationg custom columns in the admin list
		 *
		 * @param unknown $columns
		 * @return string
		 */
		public function setCustomColumns($columns) {
			//update_post_meta($new_post_id, 'wb_status', 'imported');
			//update_post_meta($new_post_id, 'is_disabled', 0);
			
			$columns ['wb_status'] = 'Type';
		
			return $columns;
		}
		
		/**
		 * show custom columns content the the admin list
		 *
		 * @param unknown $column
		 * @param unknown $post_id
		 */
		public function customColumn($column, $post_id) {
			switch ($column) {
				case 'wb_status' :
					echo get_post_meta($post_id, 'wb_status', true) == 'imported'?'Automatic':'Manually added';
					break;
			}
		}
		
		public function customAdminCss() {
			echo '<style>
			   	#wb_status {
			   		width: 10%;
				}
			   	</style>';
		}
		
		/**
		 * Reads data from Nasdaq XML feed
		 */
		public function refreshData() {
			if (get_transient('wb_news_xml_update_process'))
				return;
		
			set_transient('wb_news_xml_update_process', 1);
			$last_ct = get_option('wb_xml_news_last_cache_time');
			$last_news_timestamp = get_option('wb_xml_news_last_ts');
			
			$max_timestamp = 0;
			
			//__d('last ts: '.$last_news_timestamp);
			
			if ($last_ct+60*get_option('wb_xml_news_cache_minutes')<= time()) {
				$url = 'http://phx.corporate-ir.net/corporate.rss?c='.get_option('wb_xml_news_user_id').'&Rule=Cat=news~subcat=ALL';
				$file = @file_get_contents ($url);
				
				// this is for testing purposes. do not uncomment
				if (!$file && function_exists('tmpGetContent')) {
					$file = tmpGetContent();
				}
					
				if ($file != null) {
					
					update_option('wb_xml_news_last_cache_time', time());
		
					$i = preg_match_all('/<item>(?:.*?)<title>(.*?)<\/title>(?:.*?)<link>(.*?)<\/link>(?:.*?)<description><\!\[CDATA\[(.*?)\]\]><\/description>(?:.*?)<pubDate>(.*?)<\/pubdate>(?:.*?)<\/item>/ims', $file, $info);
		
					foreach ($info[0] as $counter=>$item) {
						$timestamp = strtotime ($info[4][$counter]);
						$max_timestamp = max($max_timestamp, $timestamp);
						
						//__d($timestamp);
						$date = date('Y-m-d H:i:s', $timestamp);
						$title = $info[1][$counter];
						$url = $info[2][$counter];
						$description = $info[3][$counter];
						$description = str_replace(']]>', ']]&gt;', $description);
		
						$excerpt_length = 25;
						$excerpt = wp_trim_words( $description, $excerpt_length, '...' );
							
						$catch_news_id = preg_match('/id=(\d*)/', $url, $a_news_url);
						$news_id = 0;
						if ($catch_news_id) {
							$news_id = $a_news_url[1];
						}
							
						
						if ($news_id > 0 && $timestamp > $last_news_timestamp) {
							/*$the_query = new WP_Query(array(
									'name'=>'wb_news_xml_'.$news_id,
									'post_type'=>'wb_news_xml'
							));*/
			
							$new_post_id = wp_insert_post(array(
									'post_content'=>$description,
									'post_name'=>'wb_news_xml_'.$news_id,
									'post_title'=>$title,
									'post_status'=>'publish',
									'post_type'=>'wb_news_xml',
									'post_excerpt'=>$excerpt,
									'post_date'=>$date
							));
							update_post_meta($new_post_id, 'external_url', $url);
							update_post_meta($new_post_id, 'wb_status', 'imported');
						}
					}
					
					if ($max_timestamp > $last_news_timestamp) {
						update_option('wb_xml_news_last_ts', $max_timestamp);
					}
					
					$_GET['wbmessage'] = 'News list updated successfully';
				}
			}
		
			delete_transient('wb_news_xml_update_process');
		}
		
		/**
		 * used to get news list for widget, custom functions etc
		 * @param number $news_to_show
		 */
		static public function getContent($news_to_show = 0) {
			if ($news_to_show == 0)
				$news_to_show = get_option('wb_xml_news_items_amount');
			
			$list = new WP_Query(array(
					'post_type'=>'wb_news_xml',
					'post_status'=>'publish',
					'order'=>'DESC',
					'orderby'=>'date',
					'posts_per_page'=>$news_to_show
			));
			
			return $list->posts;
		}
		
		/**
		 * Shortcode function
		 * @param unknown $atts
		 */
		public function showShortcode($atts) {
			$a = shortcode_atts( array(
					'count' => 10,
			), $atts );
			
			$html = self::showContent($a['count']);
			
			return $html;
		}
		
		/**
		 * inline content 
		 * @param number $news_to_show
		 */
		static public function showContent($news_to_show = 0) {
			if ($news_to_show <= 0)
				$news_to_show = get_option('wb_xml_news_items_amount');
			
			$list = self::getContent($news_to_show);
			$html = '';
				
			if (is_array($list) && sizeof($list) > 0) {
				$html.= '<div class="workboxXMLNewsContainer">';
				foreach ($list as $item) {
					$date_format = get_option('date_format');
					$url = get_post_meta($item->ID, 'external_url', true);
					$html.= '
						<div id="workboxNewsXML'.$item->ID.'Item" class="workboxNewsXMLItem">
							<span>'.date($date_format, strtotime ($item->post_date)).'</span>
							<a href="'.$url.'" target="_blank">'.$item->post_title.'</a>
							<p>'.$item->post_excerpt.'
								<a href="'.$url.'" target="_blank">Read More</a>
							</p>
						</div>';
				}
				$html.= '
 						<a href="'.get_option('wb_xml_news_read_all').'" target="_blank" class="workboxNewsXMLreadmore">All News</a>
				</div>';
			}
			
			return $html;
		}
		
		public function correctPermalink($permalink, $post, $leavename = '', $sample = '') {
			if ($post->post_type == 'wb_news_xml') {
				$permalink = get_post_meta($post->ID, 'external_url', true);
			}
			
			return $permalink;
		}
}


class workboxXMLNewsWidget extends WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array(
				'classname' => 'workboxXmlNewsWidget',
				'description' => 'Shows News from Hasdaq',
		);
		parent::__construct( 'workboxXmlNewsWidget', 'Workbox XML News Widget', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$news_to_show = isset($instance['news_amount'])?intval($instance['news_amount']):get_option('wb_xml_news_items_amount');
		$list = workboxNasdaqXMLReader::getContent($news_to_show);
		
		if (is_array($list) && sizeof($list) > 0) {
			echo $args['before_widget'];
			
			if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
			}
			
			?>
			<div class="<?php echo $args['widget_id']?> widget-<?php echo $args['widget_id']?>-<?php echo $args['id']?>">
			<?php 
				foreach ($list as $item) {
					$date_format = get_option('date_format');
					$url = get_post_meta($item->ID, 'external_url', true);
			?>
				<div id="workboxNewsXML<?php echo $item->ID?>" class="workboxNewsXMLWidgetItem">
					<span><?php echo date($date_format, strtotime ($item->post_date)) ?></span> 
					<a href="<?php echo $url; ?>" target="_blank"><?php echo $item->post_title; ?></a>
					<p><?php echo $item->post_excerpt; ?> 
						<a href="<?php echo $url; ?>" target="_blank">Read More</a>
					</p>
				</div>
			<?php 
				}
			?>
				<a href="<?php echo get_option('wb_xml_news_read_all')?>" target="_blank" class="workboxNewsXMLreadmore">All News</a>
			</div>
			<?php 
			
			echo $args['after_widget'];
		}
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : 'News';
		$news_amount = ! empty( $instance['news_amount'] ) ? $instance['news_amount'] : get_option('wb_xml_news_items_amount');
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title</label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'news_amount' ) ); ?>">News to show</label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'news_amount' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'news_amount' ) ); ?>" type="text" value="<?php echo esc_attr( $news_amount ); ?>">
		</p>
		<?php 
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['news_amount'] = ( ! empty( $new_instance['news_amount'] ) ) ? strip_tags( $new_instance['news_amount'] ) : 0;

		return $instance;
	}
}

/**
 * Test function for content generation
 */
function tmpGetContent() {
	return '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel><title>Jazz Pharmaceuticals News Release</title><link>http://phx.corporate-ir.net/Phoenix.zhtml?c=210227&amp;p=irol-news</link><description>A Collection of Jazz Pharmaceuticals News Release</description><language>en-us</language><category>Uncategorized</category><lastBuildDate>Thu, 09 Jun 2016 20:51:55 GMT</lastBuildDate><item><title>Jazz Pharmaceuticals And Celator Pharmaceuticals Announce Agreement For Jazz Pharmaceuticals To Acquire Celator For $30.25 Per Share</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2173319</link><description><![CDATA[Transaction would add VYXEOSтДв, an investigational product in development as a treatment for Acute Myeloid Leukemia (AML), to Jazz Pharmaceuticals\' portfolio

U.S. regulatory submission for VYXEOS planned by end of third quarter 2016

Jazz Pharmaceuticals to host investor conference call today, May 31, 2016 at 8:30 AM EDT (1:30 PM IST)

DUBLIN and EWING, N.J., May 31, 2016 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) and Celator Pharmaceuticals, Inc. (Nasdaq: CPXX) today announced that they have entered into a definitive agreement for Jazz Pharmaceuticals to acquire Celator for $30.25 per share in cash, or approximately $1.5 billion. 

The transaction with Celator is well-sui...]]></description><category>Uncategorized</category><guid isPermaLink="false">2173319_NEWS</guid><pubDate>Tue, 31 May 2016 07:30:11 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Upcoming Investor Conferences</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2169448</link><description><![CDATA[DUBLIN, May 18, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а 


UBS Global Healthcare Conference in New York, NY on Wednesday, May 25, 2016 at 10:00 a.m. EDT / 3:00 p.m. IST. Matt Young, executive vice president and chief financial officer, will provide an overview of the company and a business and financial update. 
Goldman Sachs 37th Annual Global Healthcare Conference in Rancho Palos Verdes, CA on Wednesday, June 8, 2016 at 11:20 a.m. PDT / 7:20 p.m. IST. Russell Cox, executive vice president and chief operating officer, will provide an overview of the...]]></description><category>Uncategorized</category><guid isPermaLink="false">2169448_NEWS</guid><pubDate>Wed, 18 May 2016 20:07:06 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces First Quarter 2016 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2167140</link><description><![CDATA[Strong Top- and Bottom-line Growth

Total Revenues of $336 Million Driven by Strong Sales of Xyrem

DUBLIN, May 10, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the first quarter of 2016 and updated financial guidance for 2016.

"During the first quarter of 2016, we executed on our business model by delivering strong top- and bottom-line growth and commenced promotion of Defitelio in the U.S. immediately following FDA approval. Defitelio is the first and only approved treatment in the U.S. for patients who develop hepatic VOD with renal or pulmonary dysfunction following hematopoietic stem-cell transplantation," said Bruce C. Cozadd,...]]></description><category>Uncategorized</category><guid isPermaLink="false">2167140_NEWS</guid><pubDate>Tue, 10 May 2016 20:05:33 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2016 First Quarter Financial Results on May 10, 2016</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2161670</link><description><![CDATA[DUBLIN, April 26, 2016 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2016 first quarter financial results on Tuesday, May 10, 2016, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EDT/9:30 p.m. IST to discuss first quarter 2016 financial results and provide a business and financial update.

Interested parties may access the live audio webcast via the Investors section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate time for any softwar...]]></description><category>Uncategorized</category><guid isPermaLink="false">2161670_NEWS</guid><pubDate>Tue, 26 Apr 2016 20:07:15 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Webcast for Defitelio┬о (defibrotide sodium) Investor Update</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2151862</link><description><![CDATA[DUBLIN, March 30, 2016 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will host a webcast on Thursday, March 31, 2016 at 4:30 p.m. EDT/9:30 p.m. IST to provide investors with an update on Defitelio, which was approved by the U.S. Food and Drug Administration on March 30, 2016 for the treatment of adult and pediatric patients with hepatic veno-occlusive disease (VOD), also known as sinusoidal obstruction syndrome, with renal or pulmonary dysfunction following hematopoietic stem-cell transplantation (HSCT).

The webcast will include an overview of HSCT and VOD from invited physician experts as well as a Defitelio overview from the company\'s senior ma...]]></description><category>Uncategorized</category><guid isPermaLink="false">2151862_NEWS</guid><pubDate>Wed, 30 Mar 2016 18:43:21 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces FDA Approval of Defitelio┬о (defibrotide sodium) for the Treatment of Hepatic Veno-Occlusive Disease (VOD) with Renal or Pulmonary Dysfunction Following Hematopoietic Stem-Cell Transplantation (HSCT)</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2151858</link><description><![CDATA[First and Only FDA-Approved Therapy for Patients with this Rare, Potentially Fatal Complication

DUBLIN, March 30, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) today announced that the United States (U.S.) Food and Drug Administration (FDA) granted marketing approval for Defitelio┬о (defibrotide sodium) for the treatment of adult and pediatric patients with hepatic VOD, also known as sinusoidal obstruction syndrome (SOS), with renal or pulmonary dysfunction following HSCT.1

"FDA\'s approval of Defitelio underscores the importance of Defitelio to children and adults as the first and only proven treatment for this rare and often deadly complication of stem-cell transplantati...]]></description><category>Uncategorized</category><guid isPermaLink="false">2151858_NEWS</guid><pubDate>Wed, 30 Mar 2016 18:33:21 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Investor Conferences in March</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2145349</link><description><![CDATA[DUBLIN, March 2, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а



Cowen and Company 36th Annual Healthcare Conference in Boston, MA on Wednesday, March 9, 2016 at 8:00 a.m. EST / 1:00 p.m. GMT.┬а Matt Young, executive vice president and chief financial officer, will provide an overview of the company and a business and financial update. 
Barclays Global Healthcare Conference in Miami, FL on Tuesday, March 15, 2016 at 8:30 a.m. EDT / 12:30 p.m. GMT.┬а Bruce Cozadd, chairman and chief executive officer, will provide an overview of the company and a business a...]]></description><category>Uncategorized</category><guid isPermaLink="false">2145349_NEWS</guid><pubDate>Wed, 02 Mar 2016 21:06:55 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Full Year And Fourth Quarter 2015 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2142362</link><description><![CDATA[Company Reports Total Revenues Increased by 13% to $1.32 Billion in 2015

Adjusted EPS of $9.52 and GAAP EPS of $5.23 in 2015

DUBLIN, Feb. 23, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the full year and the fourth quarter of 2015 and provided financial guidance for 2016.

"In 2015, we delivered solid growth on the top- and bottom-line while increasing investment in new growth opportunities for our current products and our promising R&amp;D pipeline," said Bruce C. Cozadd, chairman and chief executive officer of Jazz Pharmaceuticals plc. "We look forward to 2016 as we focus on delivering growth of our key commercial products, prep...]]></description><category>Uncategorized</category><guid isPermaLink="false">2142362_NEWS</guid><pubDate>Tue, 23 Feb 2016 21:07:05 GMT</pubDate></item><item><title>Jazz Pharmaceuticals To Present Data From Ongoing Evaluations Of Defibrotide At The 2016 BMT Tandem Meeting</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2140546</link><description><![CDATA[Jazz-Sponsored Oral Presentations Include: Results from a Pivotal Phase 3 Trial for the Treatment of VOD with MOD, Sub-Analysis Data from a Phase 3 Pediatric Trial for VOD Prophylaxis, and an Exploratory Post-Hoc Analysis from an Expanded Access Treatment of VOD / SOS Study to Evaluate Timing of Treatment Initiation

10 Jazz-Sponsored Posters Also to Be Presented

DUBLIN, Feb. 18, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) announced today that researchers will present three Jazz-sponsored oral abstracts related to the ongoing evaluation of defibrotide, an investigational medicine being studied in the United States (U.S.) for the treatment of adult and pediatric patients ...]]></description><category>Uncategorized</category><guid isPermaLink="false">2140546_NEWS</guid><pubDate>Thu, 18 Feb 2016 14:06:59 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2015 Fourth Quarter and Full Year Financial Results on February 23, 2016</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2136935</link><description><![CDATA[DUBLIN, Feb. 9, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2015 fourth quarter and full year financial results on Tuesday, February 23, 2016, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EST/9:30 p.m. GMT to discuss fourth quarter and full year 2015 financial results and provide a business and financial update and guidance for 2016 financial results.

Interested parties may access the live audio webcast via the Investors section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to...]]></description><category>Uncategorized</category><guid isPermaLink="false">2136935_NEWS</guid><pubDate>Tue, 09 Feb 2016 21:06:42 GMT</pubDate></item><item><title>Results from Phase 3 Trial of Defibrotide for the Treatment of Severe Veno-Occlusive Disease and Multi-Organ Failure Published Online in BLOOD</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2134066</link><description><![CDATA[Data show that defibrotide improved survival and complete response compared with historical controls


    
      
        

      
    
    


DUBLIN, Feb. 1, 2016 /PRNewswire/ --&nbsp;Jazz Pharmaceuticals plc&nbsp;(Nasdaq: JAZZ) today announced that data from the phase 3 pivotal study of defibrotide were published online in BLOOD, the Journal of the American Society of Hematology&nbsp;(ASH).&nbsp; The data demonstrated that defibrotide use in patients with hepatic veno-occlusive (VOD), also known as sinusoidal obstruction syndrome (SOS), with multi-organ failure (MOF) post-hematopoietic stem-cell transplantation (HSCT) was associated with a statistically significant improvement...]]></description><category>Uncategorized</category><guid isPermaLink="false">2134066_NEWS</guid><pubDate>Mon, 01 Feb 2016 14:06:00 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Present at the J.P. Morgan Healthcare Conference on January 11</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2125986</link><description><![CDATA[DUBLIN, Jan. 4, 2016 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentation at the 34th Annual J.P. Morgan Healthcare Conference in San Francisco, CA. 

Bruce C. Cozadd, chairman and chief executive officer, will provide an overview of the company and provide a business and financial update at the conference on Monday, January 11, 2016 at 10:00 a.m. PST / 6:00 p.m. GMT. 

A live audio webcast of the presentation may be accessed from the Investors section of the Jazz Pharmaceuticals website at www.jazzpharma.com.┬а Please connect to the website prior to the start of the presentation to ensure adequate time for ...]]></description><category>Uncategorized</category><guid isPermaLink="false">2125986_NEWS</guid><pubDate>Mon, 04 Jan 2016 21:06:32 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Third Quarter 2015 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2110678</link><description><![CDATA[Third Quarter 2015 Revenues Increase 11 Percent to $341 Million

Strong Top-Line Growth Driven by Xyrem and Erwinaze Sales

DUBLIN, Nov. 9, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the third quarter of 2015 and updated financial guidance for 2015.

"During the quarter, we remained focused on our mission of delivering important and meaningful products to patients. We are pleased that we have received FDA Priority Review status on our defibrotide NDA, an important step toward our objective to bring defibrotide to patients in the U.S. for the treatment of hepatic veno-occlusive disease with evidence of multi-organ dysfunction, a rar...]]></description><category>Uncategorized</category><guid isPermaLink="false">2110678_NEWS</guid><pubDate>Mon, 09 Nov 2015 21:06:05 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Three Upcoming Investor Conferences</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2106791</link><description><![CDATA[DUBLIN, Nov. 4, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentation at three upcoming investor conferences.


24th Annual Credit Suisse Healthcare Conference in Scottsdale, AZ on Wednesday, November 11, 2015 at 11:00 a.m. EST / 4:00 p.m. GMT. Russell Cox, executive vice president and chief operating officer, will provide an overview of the company and a business and financial update. 
Jefferies Autumn 2015 Global Healthcare Conference in London, England on Thursday, November 19, 2015 at 10:40 a.m. GMT / 5:40 a.m. EST. Iain McGill, senior vice president, Jazz Pharmaceuticals Europe and rest of world, w...]]></description><category>Uncategorized</category><guid isPermaLink="false">2106791_NEWS</guid><pubDate>Wed, 04 Nov 2015 21:06:14 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2015 Third Quarter Financial Results on November 9, 2015</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2102317</link><description><![CDATA[DUBLIN, Oct. 26, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2015 third quarter financial results on Monday, November 9, 2015, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EST/9:30 p.m. GMT to discuss 2015 third quarter financial results and provide a business and financial update.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate time f...]]></description><category>Uncategorized</category><guid isPermaLink="false">2102317_NEWS</guid><pubDate>Mon, 26 Oct 2015 20:06:50 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces U.S. FDA Acceptance for Filing with Priority Review of NDA for Defibrotide for Hepatic Veno-Occlusive Disease</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2091782</link><description><![CDATA[-- FDA Decision on Approval Expected by March 31, 2016 --

DUBLIN, Sept. 30, 2015 /PRNewswire/ --&nbsp;Jazz Pharmaceuticals plc&nbsp;(Nasdaq: JAZZ) today announced that the United States (U.S.) Food and Drug Administration (FDA) has accepted for filing with Priority Review its recently submitted New Drug Application (NDA) for defibrotide.&nbsp; Defibrotide is an investigational agent proposed for the treatment of patients with hepatic veno-occlusive disease (VOD), also known as sinusoidal obstruction syndrome (SOS), with evidence of multi-organ dysfunction (MOD) following hematopoietic stem-cell transplantation (HSCT).&nbsp;&nbsp;


Priority Review status is designated for drugs that ma...]]></description><category>Uncategorized</category><guid isPermaLink="false">2091782_NEWS</guid><pubDate>Wed, 30 Sep 2015 12:31:00 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Second Quarter 2015 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2076213</link><description><![CDATA[Strong Top-Line and Bottom-Line Growth

Total Revenues of $334 Million, Driven by Strong Sales of Xyrem

Completed Submission of Defibrotide Rolling NDA

DUBLIN, Aug. 5, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the second quarter of 2015 and reaffirmed financial guidance for 2015.

"Our second quarter results reflect strong top- and bottom-line growth, strong margins, and continued investment in our commercial and R&amp;D portfolios to support our long-term growth strategy," said Bruce C. Cozadd, chairman and chief executive officer of Jazz Pharmaceuticals plc. "We made significant progress toward this year\'s research and develo...]]></description><category>Uncategorized</category><guid isPermaLink="false">2076213_NEWS</guid><pubDate>Wed, 05 Aug 2015 20:07:04 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2015 Second Quarter Financial Results on August 5, 2015</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2070109</link><description><![CDATA[DUBLIN, July 22, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2015 second quarter financial results on Wednesday, August 5, 2015, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EDT/9:30 p.m. IST to discuss 2015 second quarter financial results and provide a business and financial update.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate tim...]]></description><category>Uncategorized</category><guid isPermaLink="false">2070109_NEWS</guid><pubDate>Wed, 22 Jul 2015 20:05:51 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Present at Cantor Fitzgerald Healthcare Conference on July 8</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2064246</link><description><![CDATA[DUBLIN, July 1, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentation at the Cantor Fitzgerald Healthcare Conference in New York, NY.┬а 



Russell Cox, executive vice president and chief operating officer, will provide an overview of the company and a business and financial update on Wednesday, July 8, 2015 at 9:30 a.m. EDT / 2:30 p.m. IST.┬а 

A live audio webcast of the presentation may be accessed from the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com. ┬аPlease connect to the website prior to the start of the presentation to ensure adequate time for an...]]></description><category>Uncategorized</category><guid isPermaLink="false">2064246_NEWS</guid><pubDate>Wed, 01 Jul 2015 20:05:27 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces First Patients Enrolled in Phase 3 Clinical Development Program Evaluating JZP-110 as a Potential Treatment of Excessive Daytime Sleepiness (EDS) Associated with Narcolepsy or with Obstructive Sleep Apnea (OSA)</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2057180</link><description><![CDATA[Jazz Advances its Clinical Development Program in Sleep and Narcolepsy

DUBLIN, June 8, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) announced today that the first patients have been enrolled in a Phase 3 clinical development program evaluating the safety and efficacy of its investigational drug candidate, JZP-110, as a wake-promoting agent in the treatment of excessive daytime sleepiness (EDS) in adult patients with narcolepsy or with obstructive sleep apnea (OSA).┬а The JZP-110 clinical development program includes three Phase 3 studies being conducted in the United States (U.S.), Canada and the European Union (EU).┬а The program also includes an open-label extension study ...]]></description><category>Uncategorized</category><guid isPermaLink="false">2057180_NEWS</guid><pubDate>Mon, 08 Jun 2015 13:06:57 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Present Abstracts from Ongoing Evaluations of Xyrem┬о (sodium oxybate) at SLEEP 2015</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2056496</link><description><![CDATA[DUBLIN, June 4, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) will present abstracts related to the ongoing clinical evaluation of Xyrem┬о (sodium oxybate) oral solution, a U.S. Food and Drug Administration (FDA) approved treatment for both excessive daytime sleepiness (EDS) and cataplexy in narcolepsy, at the 29th Annual SLEEP Meeting of the Associated Professional Sleep Societies (APSS), June 6-10, 2015 in Seattle, Washington.┬а 

"Xyrem is used to treat two common symptoms of narcolepsy -- cataplexy and EDS. The abstracts to be presented at SLEEP 2015 demonstrate Jazz\'s commitment to continuing clinical research that will help increase the sleep community\'s scientific and c...]]></description><category>Uncategorized</category><guid isPermaLink="false">2056496_NEWS</guid><pubDate>Thu, 04 Jun 2015 13:06:30 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Investor Conferences</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2048771</link><description><![CDATA[DUBLIN, May 15, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а 




UBS Global Healthcare Conference in New York, NY on Wednesday, May 20, 2015 at 8:00 a.m. EDT / 1:00 p.m. IST.┬а Bruce Cozadd, chairman and chief executive officer, will provide an overview of the company and a business and financial update. 
Goldman Sachs 36th Annual Global Healthcare Conference in Rancho Palos Verdes, CA on Wednesday, June 10, 2015 at 10:40 a.m. PDT / 6:40 p.m. IST.┬а Russell Cox, executive vice president and chief operating officer, will provide an overview of the company a...]]></description><category>Uncategorized</category><guid isPermaLink="false">2048771_NEWS</guid><pubDate>Fri, 15 May 2015 20:06:43 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces First Quarter 2015 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2045684</link><description><![CDATA[First Quarter 2015 Total Revenues of $309 Million, Driven by Strong Sales of Xyrem, Erwinaze and Defitelio

DUBLIN, May 7, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the first quarter ended March┬а31, 2015 and reaffirmed financial guidance for 2015.

"We are pleased with our strong top-line performance during the first quarter, driven by sales growth of our key products," said Bruce C. Cozadd, chairman and chief executive officer of Jazz Pharmaceuticals plc. "For 2015, we will remain focused on execution of our key objectives, including advancing our development pipeline, completing the rolling NDA submission for defibrotide, prepari...]]></description><category>Uncategorized</category><guid isPermaLink="false">2045684_NEWS</guid><pubDate>Thu, 07 May 2015 20:05:36 GMT</pubDate></item><item><title>Jazz Pharmaceuticals and Concert Pharmaceuticals Provide JZP-386 Program Update</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2045672</link><description><![CDATA[Phase 1 Results Support Further Evaluation of JZP-386
    
    DUBLIN &amp; LEXINGTON, Mass.--(BUSINESS WIRE)--May 7, 2015--
      Jazz
      Pharmaceuticals plc (NASDAQ: JAZZ) and Concert
      Pharmaceuticals, Inc. (NASDAQ: CNCE) today announced results from
      the recently completed Phase 1 clinical study of JZP-386, a
      deuterium-containing analog of sodium oxybate. The Phase 1 study
      evaluated the safety, pharmacokinetics and pharmacodynamics (PD) of
      JZP-386 in 30 healthy volunteers.
    

      Clinical data from this Phase 1 study demonstrated that JZP-386 provided
      favorable deuterium-related effects, including higher serum
      concentrations an...]]></description><category>Uncategorized</category><guid isPermaLink="false">2045672_NEWS</guid><pubDate>Thu, 07 May 2015 20:05:35 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2015 First Quarter Financial Results on May 7, 2015</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2039641</link><description><![CDATA[DUBLIN, April 23, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2015 first quarter financial results on Thursday, May 7, 2015, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EDT/9:30 p.m. IST to discuss first quarter 2015 financial results and provide a business and financial update.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate time for...]]></description><category>Uncategorized</category><guid isPermaLink="false">2039641_NEWS</guid><pubDate>Thu, 23 Apr 2015 20:05:33 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Full Year And Fourth Quarter 2014 Financial Results</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2019653</link><description><![CDATA[Company Reports Total Revenues of $1.17 Billion in 2014 Driven by Strong Sales of Xyrem, Erwinaze and Defitelio

Adjusted EPS of $8.43 and GAAP EPS of $0.93 in 2014

DUBLIN, Feb. 24, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the full year and the fourth quarter ended December 31, 2014 and provided financial guidance for 2015.

"2014 was an outstanding year for Jazz Pharmaceuticals as we executed on our growth strategy, delivered strong sales of our key products and further diversified our product portfolio and expanded our development pipeline through completion of three transactions," said Bruce C. Cozadd, chairman and chief exec...]]></description><category>Uncategorized</category><guid isPermaLink="false">2019653_NEWS</guid><pubDate>Tue, 24 Feb 2015 21:05:17 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Investor Conferences in March</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2019113</link><description><![CDATA[DUBLIN, Feb. 23, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а 


Cowen and Company 35th Annual Healthcare Conference in Boston, MA on Monday, March 2, 2015 at 1:30 p.m. EST / 6:30 p.m. GMT. Bruce Cozadd, chairman and chief executive officer, will provide an overview of the company and a business and financial update. 
Barclays Global Healthcare Conference in Miami, FL on Tuesday, March 10, 2015 at 2:05 p.m. EDT / 6:05 p.m. GMT. Matt Young, executive vice president and chief financial officer, will provide an overview of the company and a business and fina...]]></description><category>Uncategorized</category><guid isPermaLink="false">2019113_NEWS</guid><pubDate>Mon, 23 Feb 2015 21:06:09 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Launches Educational Campaign to Raise Awareness of Hepatic Veno-Occlusive Disease (VOD)</title><link>http://phx.corporate-ir.net/External.File?item=UGFyZW50SUQ9MjcwMjI4fENoaWxkSUQ9LTF8VHlwZT0z&amp;t=1</link><description><![CDATA[]]></description><category>Uncategorized</category><guid isPermaLink="false">2015990_NEWS</guid><pubDate>Thu, 12 Feb 2015 13:35:00 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Present Data on Defibrotide, an Investigational Treatment, in Patients with Hepatic Veno-Occlusive Disease (VOD) at BMT Tandem Meetings</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2016197</link><description><![CDATA[Three Oral Presentations Provide New Analyses of the Efficacy and Safety of Defibrotide in Patients with VOD, Including Post-Hoc Sub-Group Analyses in Children, Adults, and Allograft and Autograft Recipients

DUBLIN, Feb. 12, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) announced today that researchers will present data on the use of defibrotide, an investigational medicine being studied in the United States (U.S.) for the treatment of hepatic veno-occlusive disease (VOD), a rare, potentially life-threatening, early complication in patients undergoing hematopoietic stem-cell transplantation (HSCT) therapy.┬а The three presentations include an update from an ongoing treatment...]]></description><category>Uncategorized</category><guid isPermaLink="false">2016197_NEWS</guid><pubDate>Thu, 12 Feb 2015 13:30:25 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2014 Fourth Quarter and Full Year Financial Results on February 24, 2015</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2015427</link><description><![CDATA[DUBLIN, Feb. 10, 2015 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2014 fourth quarter and full year financial results on Tuesday, February 24, 2015, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EST/9:30 p.m. GMT to discuss fourth quarter and full year 2014 financial results and provide a business and financial update and guidance for 2015 financial results.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the web...]]></description><category>Uncategorized</category><guid isPermaLink="false">2015427_NEWS</guid><pubDate>Tue, 10 Feb 2015 21:06:35 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Present at J.P. Morgan Healthcare Conference on January 12</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2003228</link><description><![CDATA[DUBLIN, Jan. 5, 2015 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentation at the 33rd Annual J.P. Morgan Healthcare Conference in San Francisco, CA.┬а 

Bruce C. Cozadd, chairman and chief executive officer, will provide an overview of the company and provide a business and financial update at the conference on Monday, January 12, 2015 at 10:00 a.m. PST / 6:00 p.m. GMT.

A live audio webcast of the presentation may be accessed from the Investors section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com. Please connect to the website prior to the start of the presentation to ensure adequate t...]]></description><category>Uncategorized</category><guid isPermaLink="false">2003228_NEWS</guid><pubDate>Mon, 05 Jan 2015 21:05:29 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Receives FDA Approval For Intravenous Administration Of Erwinaze┬о (asparaginase Erwinia chrysanthemi)</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=2001288</link><description><![CDATA[Expanded U.S. Labeling Offers an Alternative Method to Administer Erwinaze to Patients with ALL

DUBLIN, Dec. 19, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the U.S. Food and Drug Administration (FDA) approved the intravenous administration of Erwinaze┬о (asparaginase Erwinia chrysanthemi).┬а Erwinaze is indicated as a component of a multi-agent chemotherapeutic regimen for the treatment of patients with acute lymphoblastic leukemia (ALL) who have developed hypersensitivity to E. coli-derived asparaginase1. 

"Administration of Erwinaze through an intravenous infusion provides physicians another option for patients, including those who cannot tolerate...]]></description><category>Uncategorized</category><guid isPermaLink="false">2001288_NEWS</guid><pubDate>Sat, 20 Dec 2014 00:05:32 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Initiates Rolling NDA Submission For Defibrotide For The Treatment Of Severe Hepatic Veno-Occlusive Disease</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1996956</link><description><![CDATA[Expects to complete submission in the first half of 2015

DUBLIN, Dec. 11, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced the initiation of a rolling submission of a New Drug Application (NDA) to the United States (U.S.) Food and Drug Administration (FDA) for defibrotide for the treatment of severe hepatic veno-occlusive disease (VOD) in patients undergoing hematopoietic stem-cell transplantation (HSCT) therapy.┬а Defibrotide has been granted Fast Track Designation to treat severe VOD by the FDA.

"Our start of the NDA submission for defibrotide marks an important step forward in our efforts to provide a treatment option for patients in the U.S. who develop t...]]></description><category>Uncategorized</category><guid isPermaLink="false">1996956_NEWS</guid><pubDate>Thu, 11 Dec 2014 21:05:27 GMT</pubDate></item><item><title>Jazz Pharmaceuticals and Concert Pharmaceuticals Provide JZP-386 Program Update</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1996088</link><description><![CDATA[DUBLIN &amp; LEXINGTON, Mass.--(BUSINESS WIRE)--Dec. 9, 2014--
      Jazz
      Pharmaceuticals plc (NASDAQ:JAZZ) and Concert
      Pharmaceuticals, Inc. (NASDAQ:CNCE) today announced that Phase 1
      clinical data generated to date supports completing the Phase 1
      evaluation of JZP-386 at the originally planned highest dose, which was
      not administered in the first Phase 1 trial due to a technical dosing
      issue. The existing Phase 1 clinical data was generated in a
      first-in-human trial evaluating the safety, pharmacokinetics, and
      pharmacodynamics of JZP-386; enrollment was completed in the third
      quarter. A second Phase 1 trial evaluating JZP-386 ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1996088_NEWS</guid><pubDate>Tue, 09 Dec 2014 13:30:39 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces First Patients Enrolled in Phase 3 Trial of Xyrem┬о (Sodium Oxybate) In Children And Adolescents Who Have Narcolepsy With Cataplexy</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1994332</link><description><![CDATA[Trial Initiated in Response to a FDA Pediatric Written Request to Study Xyrem in Children and Adolescents

DUBLIN, Dec. 2, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the first patients have been enrolled in a Phase 3 clinical trial to assess the safety and efficacy of Xyrem┬о (sodium oxybate) in children and adolescents aged seven to 17 who have narcolepsy with cataplexy. ┬а

Xyrem is the only U.S. Food and Drug Administration (FDA) approved treatment for narcolepsy with cataplexy in adults.┬а The FDA approval was based on clinical data in primarily adult patients.┬а While there has been a great deal of interest from the narcolepsy community to understa...]]></description><category>Uncategorized</category><guid isPermaLink="false">1994332_NEWS</guid><pubDate>Tue, 02 Dec 2014 21:05:38 GMT</pubDate></item><item><title>Jazz Pharmaceuticals To Present New Analysis Of Defibrotide Data In Patients With Hepatic Veno-Occlusive Disease (VOD) At ASH Annual Meeting</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1994331</link><description><![CDATA[Posters Include an Epidemiological Measure of the Effectiveness Analysis of Defibrotide from a Phase 3 Trial in Severe Hepatic VOD, as well as Updates from an International Compassionate Use Program and a U.S. Treatment IND Study

DUBLIN, Dec. 2, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) announced today that researchers will present the results of a number needed to treat (NNT; an epidemiological measure of effectiveness) analysis from a historically controlled Phase 3 clinical trial evaluating the use of defibrotide for the treatment of severe hepatic veno-occlusive disease (severe VOD or sVOD) in patients undergoing hematopoietic stem-cell transplantation (HSCT) therap...]]></description><category>Uncategorized</category><guid isPermaLink="false">1994331_NEWS</guid><pubDate>Tue, 02 Dec 2014 21:05:38 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Investor Conferences</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1993057</link><description><![CDATA[DUBLIN, Nov. 25, 2014 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а 


31st NASDAQ Investor Program in London, UK on Tuesday, December 2, 2014 at 6:45 a.m. EST / 11:45 a.m. GMT. Iain McGill, senior vice president and head of EUSA International, will provide an overview of the company and a business and financial update. 
26th Annual Piper Jaffray Healthcare Conference in New York, NY on Tuesday, December 2, 2014 at 2:00 p.m. EST / 7:00 p.m. GMT. Bruce Cozadd, chairman and chief executive officer, will provide an overview of the company and a business and financ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1993057_NEWS</guid><pubDate>Tue, 25 Nov 2014 21:05:24 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation in Two Investor Conferences</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1987052</link><description><![CDATA[DUBLIN, Nov. 6, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentations at two upcoming investor conferences.┬а 


Credit Suisse Healthcare Conference in Phoenix, AZ on Wednesday, November 12, 2014 at 12:30 p.m. EST / 5:30 p.m. GMT.┬а Matthew Young, senior vice president and chief financial officer, will provide an overview of the company and a business and financial update.┬а 
Stifel Healthcare Conference in New York, NY on Wednesday, November 19, 2014 at 10:55 a.m. EST / 3:55 p.m. GMT.┬а Russell Cox, executive vice president and chief operating officer, will provide an overview of the company and a busines...]]></description><category>Uncategorized</category><guid isPermaLink="false">1987052_NEWS</guid><pubDate>Thu, 06 Nov 2014 21:05:47 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Third Quarter 2014 Financial Results And Updated Guidance</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1985681</link><description><![CDATA[Company Reports Third Quarter 2014 Total Revenues of $307 Million, Driven by Strong Sales of Xyrem, Erwinaze and Defitelio

Adjusted EPS of $2.33 and GAAP EPS of $0.41

DUBLIN, Nov. 4, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the third quarter ended September 30, 2014 and updated financial guidance for full year 2014.

"We successfully executed on key goals across the business, from strong sales growth of Xyrem, Erwinaze and Defitelio, to advances in our clinical development programs, and to furtherance of our regulatory efforts in preparation for the planned submission of a new drug application for defibrotide in the U.S. in the...]]></description><category>Uncategorized</category><guid isPermaLink="false">1985681_NEWS</guid><pubDate>Tue, 04 Nov 2014 21:05:41 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2014 Third Quarter Financial Results on November 4, 2014</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1979988</link><description><![CDATA[DUBLIN, Oct. 21, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2014 third quarter financial results on Tuesday, November 4, 2014, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EST/9:30 p.m. GMT to provide a business and financial update and discuss third quarter 2014 financial results.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate time ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1979988_NEWS</guid><pubDate>Tue, 21 Oct 2014 20:05:54 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Pricing of $500 Million of Exchangeable Senior Notes</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1957020</link><description><![CDATA[DUBLIN, Aug. 8, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that Jazz Investments I Limited, its wholly-owned subsidiary (the "Issuer"), priced its previously announced offering of $500 million aggregate principal amount of exchangeable senior notes due 2021. ┬аThe Issuer has also granted the initial purchasers of the notes a 30-day option to purchase up to an additional $75 million aggregate principal amount of notes from the Issuer solely to cover over-allotments, if any. 

The notes, which will be general unsecured obligations of the Issuer, are being sold in a private offering to qualified institutional buyers pursuant to Rule 144A under the Securities A...]]></description><category>Uncategorized</category><guid isPermaLink="false">1957020_NEWS</guid><pubDate>Fri, 08 Aug 2014 10:30:24 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Proposed Offering of $500 Million of Exchangeable Senior Notes</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1956090</link><description><![CDATA[DUBLIN, Aug. 6, 2014 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that Jazz Investments I Limited, its wholly-owned subsidiary (the "Issuer"), intends to offer, subject to market conditions and other factors, $500 million aggregate principal amount of exchangeable senior notes due 2021 in a private offering to qualified institutional buyers pursuant to Rule 144A under the Securities Act of 1933, as amended (the "Securities Act").┬а In connection with the offering, the Issuer expects to grant the initial purchasers an option to purchase up to an additional $75 million aggregate principal amount of such notes solely to cover over-allotments, if any. 

The notes will...]]></description><category>Uncategorized</category><guid isPermaLink="false">1956090_NEWS</guid><pubDate>Wed, 06 Aug 2014 20:05:58 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Second Quarter 2014 Financial Results And Updated Guidance</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1955555</link><description><![CDATA[Company Reports Second Quarter 2014 Total Revenues of $291 Million, Driven by Strong Sales of Xyrem, Erwinaze and Defitelio

Adjusted EPS of $2.05 and GAAP EPS of $0.70

DUBLIN, Aug. 5, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced financial results for the second quarter ended June 30, 2014 and updated financial guidance for 2014.

"Strong underlying demand for our products and the start of our┬аEuropean Union launch of Defitelio led to significant revenue growth in the second quarter," said Bruce C. Cozadd, chairman and chief executive officer of Jazz Pharmaceuticals plc. "We have successfully completed three transactions in 2014, including our recently a...]]></description><category>Uncategorized</category><guid isPermaLink="false">1955555_NEWS</guid><pubDate>Tue, 05 Aug 2014 20:05:58 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Completes Acquisition Of Rights To Defibrotide In The Americas From Sigma-Tau Pharmaceuticals, Inc.</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1955513</link><description><![CDATA[Jazz Pharmaceuticals owns worldwide rights to defibrotide

DUBLIN, Aug. 5, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced the closing of its acquisition of rights to defibrotide in the United States (U.S.) and all other countries in the Americas from Sigma-Tau Pharmaceuticals, Inc. (Sigma-Tau).┬а Jazz Pharmaceuticals now owns worldwide rights to defibrotide.

Defibrotide is a novel product that is marketed by Jazz Pharmaceuticals in the European Union (EU) under the name Defitelio┬о for the treatment of severe hepatic veno-occlusive disease (VOD) in patients over one month of age undergoing hematopoietic stem cell transplantation (HSCT) therapy. ┬аIn the U.S., ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1955513_NEWS</guid><pubDate>Tue, 05 Aug 2014 20:01:56 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Participation at Canaccord Genuity Conference on August 13</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1954850</link><description><![CDATA[DUBLIN, Aug. 4, 2014 /PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company will be webcasting its corporate presentation at the Canaccord Genuity 34th Annual Growth Conference in Boston, MA on Wednesday, August 13, 2014 at 9:00 a.m. EDT / 2:00 p.m. IST.┬а Bruce Cozadd, chairman and chief executive officer, will provide an overview of the company and a business and financial update.

A live audio webcast of the presentation may be accessed from the Investors section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com. Please connect to the website prior to the start of the presentation to ensure adequate time for any software downloads that m...]]></description><category>Uncategorized</category><guid isPermaLink="false">1954850_NEWS</guid><pubDate>Mon, 04 Aug 2014 20:10:29 GMT</pubDate></item><item><title>Jazz Pharmaceuticals to Report 2014 Second Quarter Financial Results on August 5, 2014</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1949918</link><description><![CDATA[DUBLIN, July 22, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that it will report its 2014 second quarter financial results on Tuesday, August 5, 2014, after the close of the financial markets.┬а Company management will host a live audio webcast immediately following the announcement at 4:30 p.m. EDT/9:30 p.m. IST to provide a business and financial update and discuss second quarter 2014 financial results.

Interested parties may access the live audio webcast via the Investors &amp; Media section of the Jazz Pharmaceuticals website at www.jazzpharmaceuticals.com.┬а Please connect to the website prior to the start of the conference call to ensure adequate time ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1949918_NEWS</guid><pubDate>Tue, 22 Jul 2014 20:05:22 GMT</pubDate></item><item><title>Jazz Pharmaceuticals and Concert Pharmaceuticals Provide a Phase 1 Clinical Trial Update on JZP-386</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1949446</link><description><![CDATA[DUBLIN &amp; LEXINGTON, Mass.--(BUSINESS WIRE)--Jazz  Pharmaceuticals plc (JAZZ) and Concert  Pharmaceuticals, Inc. (CNCE) today announced the initiation  of the first Phase 1 clinical trial of JZP-386, a deuterium-containing  analog of sodium oxybateтАФthe active ingredient in Xyrem┬о (sodium  oxybate) oral solution.  The Phase 1 clinical trial is designed to assess the safety,  pharmacokinetics (PK), and pharmacodynamics (PD) of JZP-386, and  includes Xyrem as an active control. The study is expected to enroll up  to 28 healthy subjects at a single center in Europe. The results of the  study are intended to assess the PK/PD profile of JZP-386 to identify a  safe and tolerable dose or doses of...]]></description><category>Uncategorized</category><guid isPermaLink="false">1949446_NEWS</guid><pubDate>Mon, 21 Jul 2014 20:53:00 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Announces Agreement To Acquire Rights To Defibrotide In The Americas From Sigma-Tau Pharmaceuticals, Inc.</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1944485</link><description><![CDATA[Jazz Pharmaceuticals to own worldwide rights to defibrotide at closing

Investor conference call to be held today, July 2 at 8:30 AM EDT/1:30 PM IST

DUBLIN, July 2, 2014 ┬а/PRNewswire/ -- Jazz Pharmaceuticals plc (Nasdaq: JAZZ) today announced that the company has signed a definitive agreement with Sigma-Tau Pharmaceuticals, Inc. (Sigma-Tau) under which a subsidiary of Jazz Pharmaceuticals plc (Jazz) will acquire from Sigma-Tau rights to defibrotide in the United States (U.S.) and all other countries in the Americas. Sigma-Tau holds rights to market defibrotide in the Americas under an agreement with Gentium S.p.A., which was acquired by Jazz earlier this year. Defibrotide is a novel prod...]]></description><category>Uncategorized</category><guid isPermaLink="false">1944485_NEWS</guid><pubDate>Wed, 02 Jul 2014 12:00:00 GMT</pubDate></item><item><title>Jazz Pharmaceuticals Presents JZP-110 Phase 2b Data For The Treatment Of EDS Symptoms In Adults With Narcolepsy</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1936467</link><description><![CDATA[Study Demonstrated Robust Alerting Effect Consistent with Phase 2a Results

Planning Phase 3 Clinical Development Program

DUBLIN, June 2, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc (Nasdaq: JAZZ) today presented data from the Phase 2b study evaluating JZP-110 (formerly known as ADX-N05) as a potential new treatment for the symptoms of excessive daytime sleepiness (EDS) in adults with narcolepsy. ┬аIn the study, all primary and secondary endpoints were met and patients treated with JZP-110 experienced statistically significant improvements in objective and subjective symptoms of EDS.┬а Based on these data, Jazz Pharmaceuticals plans to evaluate JZP-110 in Phase 3 clinical studies in pati...]]></description><category>Uncategorized</category><guid isPermaLink="false">1936467_NEWS</guid><pubDate>Mon, 02 Jun 2014 18:40:09 GMT</pubDate></item><item><title>Jazz Pharmaceuticals To Present Data On Compound In Sleep Pipeline During APSS Annual SLEEP Meeting</title><link>http://phx.corporate-ir.net/phoenix.zhtml?c=210227&amp;p=RssLanding&amp;cat=news&amp;id=1934668</link><description><![CDATA[Phase 2b Data for JZP-110 in Adults with EDS in Narcolepsy Accepted as Late-Breaker Oral Presentation

Conference Call to be Held Monday, June 2 at 4:00 p.m. CDT / 10:00 p.m. IST







DUBLIN, May 27, 2014 /PRNewswire/ --┬аJazz Pharmaceuticals plc┬а(Nasdaq: JAZZ) announced today that new data for the investigational compound JZP-110 (previously ADX-N05) will be presented┬аat the 28th Associated Professional Sleep Societies (APSS) Annual SLEEP Meeting, May 31 to June 4, 2014 in Minneapolis, Minn. The JZP-110 abstract was accepted as a late-breaker oral presentation. 

The abstract titled "Efficacy and Safety of Oral ADX-N05 for the Treatment of Excessive Daytime Sleepiness in Adults ...]]></description><category>Uncategorized</category><guid isPermaLink="false">1934668_NEWS</guid><pubDate>Tue, 27 May 2014 20:05:38 GMT</pubDate></item></channel></rss></pre>
				';
}

new workboxNasdaqXMLReader();
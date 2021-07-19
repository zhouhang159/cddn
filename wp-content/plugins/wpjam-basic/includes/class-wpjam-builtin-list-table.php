<?php 
class WPJAM_Builtin_List_Table extends WPJAM_List_Table{
	public function filter_bulk_actions($bulk_actions=[]){
		return array_merge($bulk_actions, $this->bulk_actions);
	}

	public function filter_columns($columns){
		if($this->get_columns()){	// 在最后一个之前插入
			$column_names	= array_keys($columns);
			wpjam_array_push($columns, $this->get_columns(), end($column_names)); 
		}

		return $columns;
	}

	public function filter_sortable_columns($sortable_columns){
		return array_merge($sortable_columns, $this->get_sortable_columns());
	}

	public function get_custom_column_value($name, $id){
		$column_value	= call_user_func($this->value_callback, $name, $id);

		return $this->column_callback($column_value, $name, $id);
	}
}

class WPJAM_Posts_List_Table extends WPJAM_Builtin_List_Table{
	private $post_type	= '';

	public function __construct($args=[]){
		$current_screen	= get_current_screen();

		$screen_id	= $current_screen->id;
		$post_type	= $screen_id == 'upload' ? 'attachment' : $current_screen->post_type;
		$pt_obj		= get_post_type_object($post_type);

		$args['title']			= $pt_obj->label;
		$args['singular']		= $post_type;
		$args['capability']		= 'edit_post';
		$args['data_type']		= 'post_meta';
		$args['value_callback']	= ['WPJAM_Post', 'value_callback'];

		if(isset($args['actions']['add']) && empty($args['actions']['add']['capability'])){
			$args['actions']['add']['capability']	= $pt_obj->cap->create_posts;
		}

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action',	[$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
		}

		if(!wp_doing_ajax() || (wp_doing_ajax() && $_POST['action']=='inline-save')){
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		if(wp_doing_ajax() && $_POST['action'] == 'wpjam-list-table-action' && $_POST['list_action_type'] != 'form'){
			add_filter('wpjam_ajax_response',	[$this, 'filter_ajax_response']);
		}

		add_filter('request',		[$this, 'filter_request']);
		add_action('pre_get_posts',	[$this, 'pre_get_posts']);

		add_filter('bulk_actions-'.$screen_id,	[$this, 'filter_bulk_actions']);
		add_action('restrict_manage_posts',		[$this, 'add_taxonomy_dropdown'], 1);

		if($post_type == 'attachment'){
			add_filter('media_row_actions',	[$this, 'filter_row_actions'],1,2);

			add_filter('manage_media_columns',			[$this, 'filter_columns']);
			add_filter('manage_media_custom_column',	[$this, 'filter_custom_column'], 10, 2);
		}else{
			add_filter('map_meta_cap',	[$this, 'filter_map_meta_cap'], 10, 4);

			if(is_object_in_taxonomy($post_type, 'category')){
				add_filter('disable_categories_dropdown', '__return_true');
			}

			$row_actions_filter	= is_post_type_hierarchical($post_type) ? 'page_row_actions' : 'post_row_actions';

			add_filter($row_actions_filter,	[$this, 'filter_row_actions'], 1, 2);

			add_filter('manage_'.$post_type.'_posts_columns',		[$this, 'filter_columns']);
			add_action('manage_'.$post_type.'_posts_custom_column',	[$this, 'filter_custom_column'], 10, 2);

			add_filter('post_column_taxonomy_links',	[$this, 'filter_taxonomy_links'], 10, 3);
		}

		add_filter('manage_'.$screen_id.'_sortable_columns',	[$this, 'filter_sortable_columns']);

		$this->post_type	= $post_type;
		$this->model		= 'WPJAM_Post';
		$this->_args		= $this->parse_args($args);
	}

	protected function filter_fields($fields, $key, $id){
		$fields	= apply_filters_deprecated('wpjam_'.$this->post_type.'_posts_fields', [$fields, $key, $id, $this->post_type], 'WPJAM Basic 4.6');

		if($key && $id && !is_array($id)){
			$fields	= array_merge(['title'=>['title'=>$this->title.'标题', 'type'=>'view', 'value'=>get_post($id)->post_title]], $fields);
		}

		return $fields;
	}

	public function single_row($raw_item){
		global $post, $authordata;

		if(is_numeric($raw_item)){
			$post	= get_post($raw_item);
		}else{
			$post	= $raw_item;
		}

		$authordata = get_userdata($post->post_author);
		$post_type	= $post->post_type;

		if($post_type == 'attachment'){
			$wp_list_table = _get_list_table('WP_Media_List_Table', ['screen'=>get_current_screen()]);

			$post_owner = ( get_current_user_id() == $post->post_author ) ? 'self' : 'other';
			?>
			<tr id="post-<?php echo $post->ID; ?>" class="<?php echo trim( ' author-' . $post_owner . ' status-' . $post->post_status ); ?>">
				<?php $wp_list_table->single_row_columns($post); ?>
			</tr>
			<?php
		}else{
			$wp_list_table = _get_list_table('WP_Posts_List_Table', ['screen'=>get_current_screen()]);
			$wp_list_table->single_row($post);
		}
	}

	public function add_taxonomy_dropdown($post_type){
		foreach(get_object_taxonomies($post_type, 'objects') as $taxonomy => $tax_obj){
			if(empty($tax_obj->show_admin_column)){
				continue;
			}

			$filterable	= $tax_obj->filterable;

			if($taxonomy == 'category' && is_null($filterable)){
				$filterable	= true;
			}

			if(empty($filterable)){
				return;
			}

			$query_var	= $tax_obj->query_var;
			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
			$selected	= '';

			if(!empty($_REQUEST[$query_key])){
				$selected	= $_REQUEST[$query_key];
			}elseif(!empty($query_var) && !empty($_REQUEST[$query_var])){
				if($term	= get_term_by('slug', $_REQUEST[$query_var], $taxonomy)){
					$selected	= $term->term_id;
				}
			}elseif(!empty($_REQUEST['taxonomy']) && ($_REQUEST['taxonomy'] == $taxonomy) && !empty($_REQUEST['term'])){
				if($term	= get_term_by('slug', $_REQUEST['term'], $taxonomy)){
					$selected	= $term->term_id;
				}
			}

			if($tax_obj->hierarchical){
				wp_dropdown_categories([
					'taxonomy'			=> $taxonomy,
					'show_option_all'	=> $tax_obj->labels->all_items,
					'show_option_none'	=> '没有设置',
					'name'				=> $query_key,
					'selected'			=> (int)$selected,
					'hierarchical'		=> true
				]);
			}else{
				echo wpjam_get_field_html([
					'key'			=> $query_key,
					'value'			=> $selected,
					'type'			=> 'text',
					'data_type'		=> 'taxonomy',
					'taxonomy'		=> $taxonomy,
					'placeholder'	=> '请输入'.$tax_obj->label,
					'title'			=> '',
					'class'			=> ''
				]);
			}
		}
	}

	public function filter_request($query_vars){
		$tax_query	= [];

		foreach (get_object_taxonomies($this->post_type, 'objects') as $taxonomy=>$tax_obj){
			if(!$tax_obj->show_ui){
				continue;
			}

			$tax	= $taxonomy == 'post_tag' ? 'tag' : $taxonomy;

			if($tax != 'category'){
				if(!empty($_REQUEST[$tax.'_id'])){
					$query_vars[$tax.'_id']	= (int)$_REQUEST[$tax.'_id'];
				}
			}

			if(!empty($_REQUEST[$tax.'__and'])){
				$tax__and	= wp_parse_id_list($_REQUEST[$tax.'__and']);

				if(count($tax__and) == 1){
					if (!isset($_REQUEST[$tax.'__in'])){
						$_REQUEST[$tax.'__in']	= [];
					}

					$_REQUEST[$tax.'__in'][]	= absint(reset($tax__and));
				}else{
					$tax__and		= array_map('absint', array_unique($tax__and));
					$tax_query[]	= [
						'taxonomy'	=> $taxonomy,
						'terms'		=> $tax__and,
						'field'		=> 'term_id',
						'operator'	=> 'AND',
						// 'include_children'	=> false,
					];
				}
			}

			if(!empty($_REQUEST[$tax.'__in'])){
				$tax__in		= wp_parse_id_list($_REQUEST[$tax.'__in']);
				$tax__in		= array_map('absint', array_unique($tax__in));

				$tax_query[]	= [
					'taxonomy'	=> $taxonomy,
					'terms'		=> $tax__in,
					'field'		=> 'term_id'
				];
			}

			if(!empty($_REQUEST[$tax.'__not_in'])){
				$tax__not_in	= wp_parse_id_list($_REQUEST[$tax.'__not_in']);
				$tax__not_in	= array_map('absint', array_unique($tax__not_in));

				$tax_query[]	= [
					'taxonomy'	=> $taxonomy,
					'terms'		=> $tax__not_in,
					'field'		=> 'term_id',
					'operator'	=> 'NOT IN'
				];
			}
		}

		if($tax_query){
			$tax_query['relation']		= $_REQUEST['tax_query_relation'] ?? 'and'; 
			$query_vars['tax_query']	= $tax_query;
		}

		return $query_vars;
	}

	public function filter_row_actions($row_actions, $post){
		foreach($this->get_row_actions($post->ID) as $key => $row_action){
			$action	= $this->get_action($key);
			$status	= get_post_status($post);

			if($status == 'trash'){
				if(isset($action['post_status']) && in_array($status, (array)$action['post_status'])){
					$row_actions[$key]	= $row_action;
				}
			}else{
				if(!isset($action['post_status']) || in_array($status, (array)$action['post_status'])){
					$row_actions[$key]	= $row_action;
				}
			}
		}

		foreach(['trash', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['post_id'=>'ID: '.$post->ID]);
	}

	public function filter_map_meta_cap($caps, $cap, $user_id, $args){
		if($cap == 'edit_post'){
			if(empty($args[0])){
				$pt_obj	= get_post_type_object($this->post_type);
				return $pt_obj->map_meta_cap ? [$pt_obj->cap->edit_posts] : [$pt_obj->cap->$cap];
			}
		}

		return $caps;
	}

	public function filter_custom_column($name, $post_id){
		echo parent::get_custom_column_value($name, $post_id) ?? '';
	}

	public function filter_taxonomy_links($term_links, $taxonomy, $terms){
		$permastruct	= wpjam_get_permastruct($taxonomy);

		if(empty($permastruct) || strpos($permastruct, '/%'.$taxonomy.'_id%')){
			$query_var	= get_taxonomy($taxonomy)->query_var;
			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);

			foreach($terms as $i => $t){
				$query_str		= $query_var ? $query_var.'='.$t->slug : 'taxonomy='.$taxonomy.'&#038;term='.$t->slug;
				$term_links[$i]	= str_replace($query_str, $query_key.'='.$t->term_id, $term_links[$i]);
			}
		}

		return $term_links;
	}

	public function add_thumbnail_wrap($html){
		return preg_replace_callback('/<a class="row-title" href=".*?post=(\d+).*?"/is', function($matches){
			$post_id	= $matches[1];
			$thumbnail	= get_the_post_thumbnail($post_id, [50,50]) ?: '<span class="no-thumbnail">暂无图片</span>';

			if(post_type_supports($this->post_type, 'thumbnail') && current_user_can('edit_post', $post_id)){
				$thumbnail = wpjam_get_list_table_row_action('set_thumbnail',['id'=>$post_id, 'title'=>$thumbnail]);
			}

			return $thumbnail.$matches[0]; 
		}, $html);
	}

	public function filter_ajax_response($response){
		if($this->get_action('set_thumbnail') && isset($response['data'])){
			if(is_array($response['data'])){
				$response['data']	= array_map([$this, 'add_thumbnail_wrap'], $response['data']);
			}else{
				$response['data']	= $this->add_thumbnail_wrap($response['data']);
			}
		}

		return $response;
	}

	public function filter_html($html){
		if(!wp_doing_ajax()){
			if($this->get_action('add')){
				$html	= preg_replace('/<a href=".*?" class="page-title-action">.*?<\/a>/i', $this->get_row_action('add', ['class'=>'page-title-action']), $html);
			}

			$html	= $this->set_bulk_action_data_attr($html);
		}

		if($this->get_action('set_thumbnail')){
			$html	= $this->add_thumbnail_wrap($html);
		}

		return $html;
	}

	public function pre_get_posts($wp_query){
		if($sortable_columns = $this->get_sortable_columns()){
			$orderby	= $wp_query->get('orderby');

			if($orderby && is_string($orderby) && isset($sortable_columns[$orderby])){
				$orderby_field	= $this->get_column_field($orderby);
				$orderby_type	= $orderby_field['sortable_column'] ?? 'meta_value';

				if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
					$wp_query->set('meta_key', $orderby);
					$wp_query->set('orderby', $orderby_type);
				}else{
					$wp_query->set('orderby', $orderby);
				}
			}
		}
	}
}

class WPJAM_Terms_List_Table extends WPJAM_Builtin_List_Table{
	private $taxonomy	= '';

	public function __construct($args=[]){
		$current_screen	= get_current_screen();

		$screen_id	= $current_screen->id;
		$taxonomy	= $current_screen->taxonomy;
		$tax_obj	= get_taxonomy($taxonomy);

		$args['title']			= $tax_obj->label;
		$args['capability']		= $tax_obj->cap->edit_terms;
		$args['singular']		= $taxonomy;
		$args['data_type']		= 'term_meta';
		$args['value_callback']	= ['WPJAM_Term', 'value_callback'];

		if(is_taxonomy_hierarchical($taxonomy)){
			if($tax_obj->sortable){
				
				$parent	= (int)wpjam_get_data_parameter('parent');
				$level	= $parent ? ($this->get_level($parent)+1) : 0;

				$args['sortable']	= ['items'=>'tr.level-'.$level];

				add_filter('wpjam_register_list_table_action_args',	[$this, 'filter_list_table_action_args'], 10, 2);
			}
		}

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action', [$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
		}
		
		if(!wp_doing_ajax() || (wp_doing_ajax() && in_array($_POST['action'], ['inline-save-tax', 'add-tag']))){
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		if(wp_doing_ajax() && $_POST['action'] == 'wpjam-list-table-action' && $_POST['list_action_type'] != 'form'){
			add_action('wpjam_ajax_response',	[$this, 'filter_ajax_response']);
		}

		add_action('parse_term_query',	[$this, 'parse_term_query']);

		add_filter('bulk_actions'.$screen_id,	[$this, 'filter_bulk_actions']);
		add_filter($taxonomy.'_row_actions',	[$this, 'filter_row_actions'],1,2);

		add_filter('manage_'.$screen_id.'_columns',				[$this, 'filter_columns']);
		add_filter('manage_'.$taxonomy.'_custom_column',		[$this, 'filter_custom_column'],10,3);
		add_filter('manage_'.$screen_id.'_sortable_columns',	[$this, 'filter_sortable_columns']);

		$this->taxonomy		= $taxonomy;
		$this->model		='WPJAM_Term';
		$this->_args		= $this->parse_args($args);
	}

	protected function filter_fields($fields, $key, $id){
		$fields		= apply_filters_deprecated('wpjam_'.$this->taxonomy.'_terms_fields', [$fields, $key, $id, $this->taxonomy], 'WPJAM Basic 4.6');

		if($key && $id && !is_array($id)){
			$fields	= array_merge(['title'=>['title'=>$this->title, 'type'=>'view', 'value'=>get_term($id)->name]], $fields);
		}

		return $fields;
	}

	public function get_level($term_id){
		$term	= get_term($term_id);

		return ($term && $term->parent) ? count(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) : 0;
	}

	public function single_row($raw_item){
		if(is_numeric($raw_item)){
			$term	= get_term($raw_item);
		}else{
			$term	= $raw_item;
		}

		$level	= $this->get_level($term);

		$wp_list_table = _get_list_table('WP_Terms_List_Table', ['screen'=>get_current_screen()]);
		$wp_list_table->single_row($term, $level);
	}

	public function filter_list_table_action_args($args, $action_key){
		if(in_array($action_key, ['up', 'down', 'move'])){
			$args	= array_merge($args, ['row_action'=>false,	'callback'=>['WPJAM_Term', 'move']]);
		}

		return $args;
	}

	public function filter_row_actions($row_actions, $term){
		if(!in_array('slug', get_taxonomy($term->taxonomy)->supports)){
			unset($row_actions['inline hide-if-no-js']);
		}

		$row_actions	= array_merge($row_actions, $this->get_row_actions($term->term_id));

		foreach(['delete', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['term_id'=>'ID：'.$term->term_id]);
	}

	public function filter_columns($columns){
		$columns	= parent::filter_columns($columns);
		$tax_obj	= get_taxonomy($this->taxonomy);
		
		foreach(['slug', 'description'] as $key){
			if(!in_array($key, $tax_obj->supports)){
				unset($columns[$key]);
			}
		}

		return $columns;
	}

	public function filter_custom_column($value, $name, $id){
		return $this->get_custom_column_value($name, $id) ?? $value;
	}

	public function parse_term_query($term_query){
		if($sortable_columns = $this->get_sortable_columns()){
			$orderby	= $term_query->query_vars['orderby'];

			if($orderby && isset($sortable_columns[$orderby])){
				$orderby_field	= $this->get_column_field($orderby);
				$orderby_type	= $orderby_field[$orderby]['sortable_column'] ?? 'meta_value';

				if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
					$term_query->query_vars['meta_key']	= $orderby;
					$term_query->query_vars['orderby']	= $orderby_type;
				}else{
					$term_query->query_vars['orderby']	= $orderby;
				}
			}
		}
	}

	public function replace_edit_link($html){
		return preg_replace_callback('/<tr id="tag-(\d+)" class=".*?">.*?<\/tr>/is', function($matches){
			$term_id	= $matches[1];
			$query_var	= get_taxonomy($this->taxonomy)->query_var;
			$query_key	= wpjam_get_taxonomy_query_key($this->taxonomy);
			$search		= $query_var ? '?'.$query_var.'='.get_term($term_id)->slug : '?taxonomy='.$this->taxonomy.'&#038;term='.get_term($term_id)->slug;
			$replace	= '?'.$query_key.'='.$term_id;

			return str_replace($search, $replace, $matches[0]);
		}, $html);
	}

	public function add_thumbnail_wrap($html){
		return preg_replace_callback('/<strong><a class="row-title" href=".*?tag_ID=(\d+).*?"/is', function($matches){
			$term_id	= $matches[1];
			$thumb_url	= wpjam_get_term_thumbnail_url($term_id, [100, 100]);
			$thumbnail	= $thumb_url ? '<img class="wp-term-image" src="'.$thumb_url.'"'.image_hwstring(50,50).' />' : '<span class="no-thumbnail">暂无图片</span>';
			$taxonomy	= get_term($term_id)->taxonomy;
			$capability	= get_taxonomy($taxonomy)->cap->edit_terms;

			if(current_user_can($capability)){
				$thumbnail = wpjam_get_list_table_row_action('set_thumbnail', ['id'=>$term_id, 'title'=>$thumbnail]);
			}

			return $thumbnail.$matches[0];
		}, $html);
	}

	public function filter_html($html){
		$permastruct	= wpjam_get_permastruct($this->taxonomy);

		if(empty($permastruct) || strpos($permastruct, '/%'.$this->taxonomy.'_id%')){
			$html	= $this->replace_edit_link($html);
		}

		if($this->get_action('set_thumbnail')){
			$html	= $this->add_thumbnail_wrap($html);
		}

		if(!wp_doing_ajax()){
			$html	= $this->set_bulk_action_data_attr($html);
		}

		return $html;
	}

	public function filter_ajax_response($response){
		if(isset($response['data'])){
			$permastruct	= wpjam_get_permastruct($this->taxonomy);

			if(empty($permastruct) || strpos($permastruct, '/%'.$this->taxonomy.'_id%')){
				if(is_array($response['data'])){
					$response['data']	= array_map([$this, 'replace_edit_link'], $response['data']);
				}else{
					$response['data']	= $this->replace_edit_link($response['data']);
				}
			}

			if($this->get_action('set_thumbnail')){
				if(is_array($response['data'])){
					$response['data']	= array_map([$this, 'add_thumbnail_wrap'], $response['data']);
				}else{
					$response['data']	= $this->add_thumbnail_wrap($response['data']);
				}
			}	
		}

		return $response;
	}
}

class WPJAM_Users_List_Table extends WPJAM_Builtin_List_Table{
	public function __construct($args=[]){
		$current_screen	= get_current_screen();

		$screen_id	= $current_screen->id;

		$args['title']			= '用户';
		$args['singular']		= 'user';
		$args['capability']		= 'edit_user';
		$args['data_type']		= 'user_meta';
		$args['value_callback']	= ['WPJAM_User', 'value_callback'];

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action', [$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
		}

		add_filter('user_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_filter('manage_users_columns',			[$this, 'filter_columns']);
		add_filter('manage_users_custom_column',	[$this, 'filter_custom_column'],10,3);
		add_filter('manage_users_sortable_columns',	[$this, 'filter_sortable_columns']);

		$this->model	= 'WPJAM_User';
		$this->_args	= $this->parse_args($args);
	}

	protected function filter_fields($fields, $key, $id){
		if($key && $id && !is_array($id)){
			$fields	= array_merge(['name'=>['title'=>'用户', 'type'=>'view', 'value'=>get_userdata($id)->display_name]], $fields);
		}

		return $fields;
	}

	public function single_row($raw_item){
		$wp_list_table = _get_list_table('WP_Users_List_Table', ['screen'=>get_current_screen()]);

		echo $wp_list_table->single_row($raw_item);
	}

	public function filter_row_actions($row_actions, $user){
		foreach($this->get_row_actions($user->ID) as $key => $row_action){
			$action	= $this->get_action($key);

			if(!isset($action['roles']) || array_intersect($user->roles, (array)$action['roles'])){
				$row_actions[$key]	= $row_action;
			}
		}

		foreach(['delete', 'remove', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['user_id'=>'ID: '.$user->ID]);
	}

	public function filter_custom_column($value, $name, $id){
		return $this->get_custom_column_value($name, $id) ?? $value;
	}
}
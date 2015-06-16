<?php 

/*
 *  DustPressHelper
 *	
 *  Wrapper for bunch of helper functions to use
 *  with DustPress.
 * 
 */

class DustPressHelper {

	/*
	 *  Post functions
	 *	
	 *  Simplify post queries for getting meta 
	 *  data and ACF fields with single function call.
	 * 
	 */

	private $post;
	private $posts;
	
	/*
	*  get_post
	*
	*  This function will query single post and its meta.
	*  The wanted meta keys should be in an array as strings.
	*  A string 'all' returns all the meta keys and values in an associative array.
	*  If 'single' is set to true then the functions returns only the first value of the specified meta_key.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)
	*  @param	$single (boolean)
	*  @param	$metaType (string)
	*
	*  $return  post object as an associative array with meta data
	*/
	public function get_post( $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		global $post;

		$this->post = get_post( $id, 'ARRAY_A' );
		if ( is_array( $this->post ) ) {
			$this->get_post_meta( $this->post, $id, $metaKeys, $single, $metaType );
		}

		return $this->post;
	}

	/*
	*  get_acf_post
	*
	*  This function will query single post and its meta.
	*  Meta data is handled the same way as in get_post.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)
	*  @param	$single (boolean)
	*  @param	$metaType (string)
	*  @param 	$wholeFields (boolean)
	*  @param 	$recursive (boolean)
	*
	*  $return  post object as an associative array with acf fields and meta data
	*/
	public function get_acf_post( $id, $metaKeys = NULL, $single = false, $metaType = 'post', $wholeFields = false, $recursive = false ) {

		$acfpost = get_post( $id, 'ARRAY_A' );
		
		if ( is_array( $acfpost ) ) {
			$acfpost['fields'] = get_fields( $id );

			// Get fields with relational post data as whole acf object
			if ( $recursive ) {
				foreach ($acfpost['fields'] as &$field) {
					if ( is_array($field) && is_object($field[0]) ) {
						for ($i=0; $i < count($field); $i++) { 
							$field[$i] = $this->get_acf_post( $field[$i]->ID, $metaKeys, $single, $metaType, $wholeFields, $recursive );
						}
					}
				}
				
			}
			elseif ( $wholeFields ) {
				foreach($acfpost['fields'] as $name => &$field) {
					$field = get_field_object($name, $id, true);
				}
			}
			$this->get_post_meta( $acfpost, $id, $metaKeys, $single, $metaType );
		}

		$acfpost['permalink'] = get_permalink($id);


		return $acfpost;
	}

	/*
	*  get_posts
	*
	*  This function will query all posts and its meta based on given arguments.
	*  The wanted meta keys should be in an array as strings.
	*  A string 'all' returns all the meta keys and values in an associative array.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)	
	*  @param	$metaType (string)
	*
	*  @return	array of posts as an associative array with meta data
	*/
	public function get_posts( $args, $metaKeys = NULL, $metaType = 'post' ) {

		$this->posts = get_posts( $args );

		// cast post object to associative arrays
		foreach ($this->posts as &$temp) {
			$temp = (array) $temp;
		}
		
		// get meta for posts
		if ( count( $this->posts ) ) {
			$this->get_meta_for_posts( $this->posts, $metaKeys, $metaType );
			
			wp_reset_postdata();
			return $this->posts;
		}	
		else
			return false;
	}

	/*
	*  get_acf_posts
	*
	*  This function can query multiple posts which have acf fields based on given arguments.
	*  Returns all the acf fields as an array.
	*  Meta data is handled the same way as in get_posts.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)	
	*  @param	$metaType (string)
	*
	*  @return	array of posts as an associative array with acf fields and meta data
	*/
	public function get_acf_posts( $args, $metaKeys = NULL, $metaType = 'post', $wholeFields = false ) {

		$this->posts = get_posts( $args );

		// cast post object to associative arrays
		foreach ($this->posts as &$temp) {
			$temp = (array) $temp;
		}

		if ( count( $this->posts ) ) {
			// loop through posts and get all acf fields
			foreach ( $this->posts as &$p ) {								
				$p['fields'] = get_fields( $p['ID'] );
				$p['permalink'] = get_permalink( $p['ID'] );
				if($wholeFields) {
					foreach($p['fields'] as $name => &$field) {
						$field = get_field_object($name, $p['ID'], true);
					}
				}
			}

			$this->get_meta_for_posts( $this->posts, $metaKeys, $metaType );

			wp_reset_postdata();
			return $this->posts;
		}	
		else
			return false;
	}


	/*
	 *
	 * Private functions
	 *
	 */
	private function get_post_meta( &$post, $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		$meta = array();

		if ($metaKeys === 'all') {
			$meta = get_metadata( $metaType, $id );
		}
		elseif (is_array($metaKeys)) {
			foreach ($metaKeys as $key) {
				$meta[$key] = get_metadata( $metaType, $id, $key, $single );
			}
		}

		$post['meta'] = $meta;
	}

	private function get_meta_for_posts( &$posts, $metaKeys = NULL, $metaType = 'post' ) {
		if ($metaKeys === 'all') {
			// loop through posts and get the meta values
			foreach ($posts as $post) {				
				$post['meta'] = get_metadata( $metaType, $post->ID );				
			}				
		}
		elseif (is_array($metaKeys)) {
			// loop through selected meta keys
			foreach ($metaKeys as $key) {
				// loop through posts and get the meta values
				foreach ($posts as &$post) {					
					$post['meta'][$key] = get_metadata( $metaType, $post->ID, $key, $single = false);	
				}	
			}

		}		
	}

	/*
	 *  Menu functions
	 *	
	 *  These functions gather menu data to use with DustPress
	 *  helper and developers' implementations.
	 * 
	 */

	/*
	*  get_menu_as_items
	*
	*  Returns all menu items arranged in a recursive array form that's
	*  easy to use with Dust templates. Menu_name parameter is mandatory.
	*  Parent is used to get only submenu for certaing parent post ID.
	*  Override is used to make some other post than the current "active".
	*
	*  @type	function
	*  @date	16/6/2015
	*  @since	0.0.2
	*
	*  @param	$menu_name (string)
	*  @param   $parent (integer)
	*  @param	$override (integer)	
	*
	*  @return	array of menu items in a recursive array
	*/
	function get_menu_as_items( $menu_name, $parent = 0, $override = null ) {

		if ( ( $locations = get_nav_menu_locations() ) && isset( $locations[ $menu_name ] ) ) {
			$menu_object = wp_get_nav_menu_object( $locations[ $menu_name ] );
		}

		$menu_items = wp_get_nav_menu_items( $menu_object );

		if ( $menu_items ) {

			$menu = $this->build_menu( $menu_items, $parent, $override );

			if ( $index = array_search( "active", $menu ) ) {
				unset( $menu[$index] );
			}
			if ( 0 === array_search( "active", $menu ) ) {
				unset( $menu[0] );
			}

			var_dump($menu);

			return $menu;
		}
	}

	/*
	*  build_menu
	*
	*  Recursive function that builds a menu downwards from an item. Calls
	*  itself recursively in case there is a submenu under current item.
	*
	*  @type	function
	*  @date	16/6/2015
	*  @since	0.0.2
	*
	*  @param	$menu_items (array)
	*  @param	$parent (integer)
	*  @param	$override (integer)	
	*
	*  @return	array of menu items
	*/
	function build_menu( $menu_items, $parent = 0, $override = null ) {
		$tempItems = array();
		$parent_id = 0;

		if ( count( $menu_items ) > 0 ) {
			foreach ( $menu_items as $item ) {
				if ( $item->object_id == $parent ) {
					$parent_id = $item->ID;
					break;
				}
			}
		}

		if ( is_category() ) {
			global $cat;			
		}

		if ( count( $menu_items ) > 0 ) {
			foreach ( $menu_items as $item ) {
				if ( $item->menu_item_parent == $parent_id ) {
					$item->Submenu = $this->build_menu( $menu_items, $item->object_id, $override );

					if ( is_array( $item->Submenu ) && count( $item->Submenu ) > 0 ) {
						$item->classes[] = "has_submenu";
					}
					if ( is_array( $item->Submenu) && $index = array_search( "active", $item->Submenu ) ) {
						$item->classes[] = "active";
						unset( $item->Submenu[$index] );
						$tempItems[] = "active";
					}
					if ( is_array( $item->Submenu) && 0 === array_search( "active", $item->Submenu ) ) {
						$item->classes[] = "active";
						unset( $item->Submenu[0] );
						$tempItems[] = "active";
					}

					if ( ( $item->object_id == get_the_ID() ) || $item->object_id == $cat || ( $item->object_id == $override ) ) {
						$item->classes[] = "active";
						$tempItems[] = "active";
					}

					if ( is_array( $item->classes ) && count( $item->classes ) == 1 && empty( $item->classes[0] ) ) {
						unset( $item->classes );
					}

					if ( is_array( $item->classes ) ) {
						$item->classes = array_filter($item->classes);
					}

					$tempItems[] = $item;
				}
			}
		}

		return $tempItems;
	}
}
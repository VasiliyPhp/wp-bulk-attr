<?php
/*
Plugin Name: bulk add variations 
Description: Добавление атрибутов товарам
Author: Wasiliy Gerlah
Version: 1.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
add_action('admin_menu', 'x_menu', 99);
function x_menu(){
	add_submenu_page('woocommerce', 'Управление атрибутами', 'Управление атрибутами', 'manage_options', 'x_set', 'x_set');
}

function x_set(){
	global $woocommerce;
	$attr = '';
	if(isset($_POST['custom_bulk_taxonomy'])){
		$attr = $_POST['custom_bulk_taxonomy'];
		update_option('x_choosen_attribute', $_POST['custom_bulk_taxonomy']);
	}else{
		$attr = get_option('x_choosen_attribute');
	}
	$attrs = function_exists('wc_get_attribute_taxonomies')? wc_get_attribute_taxonomies() : $woocommerce->get_attribute_taxonomies();
	echo "<h4>Сhoose the property that you assign goods batch</h4>";
	echo "<form method=post >";
	echo "<ol>";
	foreach ($attrs as $a){
// x($a);
		$checked = $attr == $a->attribute_name ? ' checked ' : '' ;
		echo '<li><label><input '.$checked.' type=radio name=custom_bulk_taxonomy value="'.$a->attribute_name.'">'. $a->attribute_label  . '</label></li>';
		
		$vars = get_terms('pa_'.$a->attribute_name, array('hide_empty' => false));
		
	}
	
	
	
	echo '</ol>';
	echo '<button class=button type=submit id=save_choose >Сохранить</button>';
	echo '</form>';
};
add_action('woocommerce_product_bulk_edit_start','add_x_bulk');
add_action('wp_ajax_save_var_extra', 'save_var_extra');
add_action('wp_ajax_save_desc_extra', 'save_desc_extra');
add_action('admin_footer', function(){
	echo "<script>
	jQuery(function($){
		var loader_img = $('<img>').attr('src', '".includes_url('images/spinner.gif')."');
		var stored_prices = JSON.parse(localStorage.getItem('x_vars_prices'))
		if(stored_prices && stored_prices.length){
			var i = 0;
			$('.x_vars_li').each(function(){
					$('[type=number]',$(this)).val(stored_prices[i]);
					i++;
			  });
		}
		$('.x_save_bulk').click(function(e){
			if($('img',$(this)).length){
				return false;
			}
			var data = $(this).closest('form').serializeArray();
			
			var action = $(this).attr('data-action');
			var _this = $(this);
			var posts = [];
			var nData;
			var tmp_n;
			for(var i in data){
				if(data[i].name == 'post[]'){
				  posts.push(data[i].value);
				}else if(data[i].name == 'action'){
					data[i].value = action || 'save_bulk_extra';
				}
			}
			if(action=='save_var_extra'){
				var arr = [];
				$('.x_vars_li').each(function(){
					arr.push($('[type=number]',$(this)).val())
			  });
				localStorage.setItem('x_vars_prices', JSON.stringify(arr))				
			}
			var back = \$('<div>').css({
				position:'absolute',
				top:0,
				left:0,
				width:'100%',
				height:'100%',
				background:'#fff',
				opacity:.6,
			});
			// back.appendTo(_this);
			loader_img.appendTo(back);
			e.preventDefault();
			e.stopPropagation();
			var progress = $(this).next('.x-progress');
			var count = 0;
			for(i in posts){
				nData = data.slice(0);
				nData.push({name:'x_post',value:posts[i]})
				back.appendTo(_this);
				$.ajax({
					url : ajaxurl,
					async:false,
					data : nData,
					dataType:'html',
					success : function(res){
						console.log(res);
						progress.attr({value:++count,max:posts.length}); 
						return 
						if( ! $('#x_result').length ){
							$('<div>').attr('id','x_result').appendTo('body')
								.css({
									position:'fixed',opacity:.9,overflow:'auto',
									zIndex:99999,top:0,padding:10,
									left:0,border:'3px solid #944',resize:'both',
									background:'#eee',maxWidth:600,maxHeight:400})
						}
						$('#x_result').append(res)
					},
					complete:function(){
						// count == posts.length && back.remove();
						back.remove();
					}
				})
			}
		})
		$('.select_all_variations').click(function(e){
			e.preventDefault();
			e.stopPropagation();
			$('.x_attrs:checked').length ? $('.x_attrs').attr('checked',false) : $('.x_attrs').attr('checked',true);
		});
	});
	</script>";
	echo "<style>
	.x_bulk_editor label{
		display:inline-block !important;
	}
	.x_bulk_editor li{
		padding:3px;
		background:#efefef;
	}
	</style>";
},1000);
function save_var_extra(){
	global $wpdb;
	error_reporting('E_ALL');
	ini_set('display_errors',1);
	$req = sanitize_post($_REQUEST,'raw');
	$posts = (array)$req['x_post'];
	$vars = isset($req['x_vars']) ? ($req['x_vars']) : array();
	// j($vars);
	$choosen_attr = 'pa_' . get_option('x_choosen_attribute');
	// j($choosen_attr);
	foreach($posts as $post){
		$prod = get_product( $post);
		if($prod->product_type=='variable'){
		  $post_taxonomies = array_keys(get_the_taxonomies($post));
			if($choosen_attr && !in_array($choosen_attr, $post_taxonomies)){
				$defaults[ $choosen_attr ] = array (
					'name' => $choosen_attr,
					'value' => '',
					'position' => 1,
					'is_visible' => 1,
					'is_variation' => 1,
					'is_taxonomy' => 1,
				);
				update_post_meta( $post , '_product_attributes', $defaults );
			}
			$existing_attributes = array();
			$tmp = get_the_terms($post,$choosen_attr);
			foreach($tmp as $item){
				$existing_attributes[$item->term_id] = $item->slug;
			}
			// j(get_the_terms($post,$choosen_attr));
			// continue;
		
			// $price = 5;(float)$prod->get_variation_regular_price();
			$post_id = intval( $post );
						
			product_del_variations($post);
			// break;
			foreach($vars as $key=>$var){
				if($key=='price'){
					continue;
				}
				foreach($var as $ind=>$v){
				  $price = $vars['price'][$ind];
					$variation = array(
						'post_title'   => 'Product #' . $post_id . ' Variation',
						'post_content' => '',
						'post_status'  => 'publish',
						'post_author'  => get_current_user_id(),
						'post_parent'  => $post_id,
						'post_type'    => 'product_variation',
						'menu_order'   => -1
					);
					if(!in_array($v, $existing_attributes)){
						// wp_set_object_terms ($post_id,'simple','product_type');
						wp_set_object_terms($post, $v, $key, true);
						// echo $v , ' ' , $key, ' ', $post;
			    	$prod->variable_product_sync();
					// WC_Product_Variable::sync_attributes( $post_id );
					}
					$variation_id = wp_insert_post( $variation );
					update_post_meta($variation_id, '_price', $price);
					update_post_meta($variation_id, '_regular_price', $price);
					update_post_meta($variation_id, 'attribute_' . $key, $v);	
					update_post_meta($variation_id, '_stock_status', 'instock');
				}
				
			}
			$prod->variable_product_sync();
			// wc_delete_product_transients( $post_id );
			// WC_Product_Variable::sync( $post_id );
		}
	}
	die;
}

function product_del_variations($id){
	global $wpdb;
	$query = 'delete t1'
	  .' from '.$wpdb->postmeta . '  t1 inner join '.$wpdb->posts 
		.'  t2 on (t1.post_id=t2.id) where t2.post_type = "product_variation" and t2.post_parent =' . $id;
	$wpdb->query($query);
	$wpdb->delete($wpdb->posts, array('post_parent'=>$id, 'post_type'=>'product_variation'));
	
}

function save_desc_extra(){
	$req = sanitize_post($_REQUEST,'raw');
	$posts = $req['post'];
	array_walk($posts, function($id) use($req){
		wp_update_post(array('ID'=>$id, 'post_content'=>$req["x_content"], 'post_excerpt'=>$req['x_excerpt']));
	});

	// j($posts);
	die;
}
function add_x_bulk(){
	global $woocommerce;
	echo '<div class="x_bulk_editor inline-edit-group">';
	echo "<label><em>Description (post content)</em><textarea name=x_content ></textarea></label>";
	echo "<label><em>Short description (post excerpt)</em><textarea name=x_excerpt ></textarea></label>";
	echo "<button data-action='save_desc_extra' style='position:relative' class='button-primary button x_save_bulk'>Apply all descriptions</button>";	global $woocommerce;
  echo " <progress value=0 max=100 class=x-progress ></progress>";;
  $attrs = function_exists('wc_get_attribute_taxonomies')? wc_get_attribute_taxonomies() : $woocommerce->get_attribute_taxonomies();
	$choosen_attr = count($attrs)===1 ? $attrs[0]->attribute_name : get_option('x_choosen_attribute');
	if($choosen_attr){
		echo '<div>';
		foreach ($attrs as $key=>$a){
			if($a->attribute_name != $choosen_attr){
				continue;
			}
			echo "<div>";
			echo "<label><!--<input value='$key' type=checkbox name='attribute_names[]'> -->";
			echo  $a->attribute_label .'</label>';
			echo "<ol>" ;
			
			$vars = get_terms('pa_'.$a->attribute_name, array('hide_empty' => false));
			foreach($vars as $var){
				// x($var);
				echo '<li class="x_vars_li" >';
				echo '<label>';
				echo ' <input class="x_attrs" type=checkbox name="x_vars['.$var->taxonomy.'][]" value="'.$var->slug.'" >';
				echo  $var->name;
				echo '</label>';
				echo '<label>price: ';
				echo ' <input class="" type=number name="x_vars[price][]" style="width:80px" value="" >';
				echo '</label>';
				echo '</li>';
			}
			
			echo "</ol>";
			echo "</div>";
		}
		echo '</div>';
		if(count($vars)){
		  echo "<button class='button select_all_variations' style='margin-right:10px'>Select all</button>";
		  echo "<button data-action='save_var_extra'  style='position:relative' class='button-primary button x_save_bulk'>Apply all variations</button>";
		  echo " <progress value=0 max=100 class=x-progress ></progress>";
		}
	} else{
		echo 'There are no accessible properties of the product properties';
	}
	echo "<hr/>";
	echo '</div>';
		
}
if(!function_exists('x')){
	function x(){
		
		$ar = func_get_args();
		if(count($ar)==1){
			$ar = $ar[0];
		}
		printf('<pre>%s</pre>', print_r($ar, 1));
	}
}
if(!function_exists('j')){
	function j(){
		
		$ar = func_get_args();
		if(count($ar)==1){
			$ar = $ar[0];
		}
		x($ar);
		die;
	}

}
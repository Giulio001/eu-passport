<?php
require '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
wp_set_current_user(1);
activate_plugin('giuliomax-product-passport/giuliomax-product-passport.php');
activate_plugin('woocommerce/woocommerce.php');
wp_set_current_user(0);wp_set_current_user(1);
// Activate during this request, then initialize WooCommerce explicitly.
if(!did_action('woocommerce_init')) { WC()->init(); }
use Giuliomax\Passport\{Store,Passport,Woo};
$tests=[];function check($name,$fn){global $tests;try{$fn();$tests[]=['name'=>$name,'ok'=>true];}catch(Throwable $e){$tests[]=['name'=>$name,'ok'=>false,'error'=>$e->getMessage()];}}
function eq($a,$b){if($a!==$b)throw new RuntimeException(json_encode([$a,$b]));}
update_option('gmpp_settings',['company'=>'Woo Test','language'=>'it']);
$product=new WC_Product_Simple();$product->set_name('Woo Chair');$product->set_sku('WC-CHAIR');$product->set_description('Commercial description');$product->set_regular_price('99');$id=$product->save();
$row=[];
check('Create from WooCommerce product API',function()use($id,&$row){$row=Woo::import($id);eq($row['data']['name'],'Woo Chair');eq((int)$row['wc_id'],$id);$row=Passport::publish((int)$row['id'],(int)$row['lock_version'],'Woo publication');});
check('WooCommerce rename suggests without overwriting draft or public',function()use($product,$id,$row){$product->set_name('Changed Woo Chair');$product->save();$suggestion=Woo::import($id);eq($suggestion['suggested']['name'],'Changed Woo Chair');eq($suggestion['existing']['data']['name'],'Woo Chair');eq(Passport::published($row['uuid'])['data']['name'],'Woo Chair');eq(wc_get_product($id)->get_regular_price(),'99');});
check('WooCommerce product deletion keeps published QR',function()use($product,$row){$product->delete(true);eq(Passport::published($row['uuid'])['data']['name'],'Woo Chair');});
check('Variable product and variation inheritance',function(){ $p=new WC_Product_Variable();$p->set_name('Variable chair');$p->set_sku('VAR-CHAIR');$p->save();$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_sku('VAR-OAK');$v->set_regular_price('110');$v->save();$r=Woo::import($v->get_id());eq($r['kind'],'variant');eq(Passport::get((int)$r['parent_id'])['kind'],'model'); });
check('WooCommerce deactivation retains standalone data',function()use($row){deactivate_plugins('woocommerce/woocommerce.php');eq(Passport::published($row['uuid'])['data']['name'],'Woo Chair');});
header('Content-Type: application/json');echo wp_json_encode(['tests'=>$tests,'woo'=>WC_VERSION,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION]);

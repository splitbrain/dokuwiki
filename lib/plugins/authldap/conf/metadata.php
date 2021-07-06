<?php
$meta['server']      = array('string','_caution' => 'danger');
$meta['port']        = array('numeric','_caution' => 'danger');
$meta['usertree']    = array('string','_caution' => 'danger');
$meta['grouptree']   = array('string','_caution' => 'danger');
$meta['userfilter']  = array('string','_caution' => 'danger');
$meta['groupfilter'] = array('string','_caution' => 'danger');
$meta['version']     = array('numeric','_caution' => 'danger');
$meta['starttls']    = array('onoff','_caution' => 'danger');
$meta['referrals']   = array('multichoice','_choices' => array(-1,0,1),'_caution' => 'danger');
$meta['deref']       = array('multichoice','_choices' => array(0,1,2,3),'_caution' => 'danger');
$meta['binddn']      = array('string','_caution' => 'danger');
$meta['bindpw']      = array('password','_caution' => 'danger','_code'=>'base64');
$meta['attributes']  = array('array');
$meta['userscope']   = array('multichoice','_choices' => array('sub','one','base'),'_caution' => 'danger');
$meta['groupscope']  = array('multichoice','_choices' => array('sub','one','base'),'_caution' => 'danger');
$meta['userkey']     = array('string','_caution' => 'danger');
$meta['groupkey']    = array('string','_caution' => 'danger');
$meta['debug']       = array('onoff','_caution' => 'security');
$meta['modPass']     = array('onoff');

$meta['mapping_name'] = array('string');
//$meta['mapping']['grps']  unsupported in config manager

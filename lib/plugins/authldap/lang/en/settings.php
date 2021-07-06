<?php
$lang['server']      = 'Your LDAP server. Either hostname (<code>localhost</code>) or full qualified URL (<code>ldap://server.tld:389</code>)';
$lang['port']        = 'LDAP server port if no full URL was given above';
$lang['usertree']    = 'Where to find the user accounts. Eg. <code>ou=People, dc=server, dc=tld</code>';
$lang['grouptree']   = 'Where to find the user groups. Eg. <code>ou=Group, dc=server, dc=tld</code>';
$lang['userfilter']  = 'LDAP filter to search for user accounts. Eg. <code>(&amp;(uid=%{user})(objectClass=posixAccount))</code>';
$lang['groupfilter'] = 'LDAP filter to search for groups. Eg. <code>(&amp;(objectClass=posixGroup)(|(gidNumber=%{gid})(memberUID=%{user})))</code>';
$lang['version']     = 'The protocol version to use. You may need to set this to <code>3</code>';
$lang['starttls']    = 'Use TLS connections?';
$lang['referrals']   = 'Shall referrals be followed?';
$lang['deref']       = 'How to dereference aliases?';
$lang['binddn']      = 'DN of an optional bind user if anonymous bind is not sufficient. Eg. <code>cn=admin, dc=my, dc=home</code>';
$lang['bindpw']      = 'Password of above user';
$lang['attributes']  = 'Attributes to retrieve with the LDAP search.';
$lang['userscope']   = 'Limit search scope for user search';
$lang['groupscope']  = 'Limit search scope for group search';
$lang['userkey']     = 'Attribute denoting the username; must be consistent to userfilter.';
$lang['groupkey']    = 'Group membership from any user attribute (instead of standard AD groups) e.g. group from department or telephone number';
$lang['modPass']     = 'Can the LDAP password be changed via dokuwiki?';
$lang['debug']       = 'Display additional debug information on errors';

$lang['mapping_name'] = 'Attribute to denote fullname of user';

$lang['deref_o_0']   = 'LDAP_DEREF_NEVER';
$lang['deref_o_1']   = 'LDAP_DEREF_SEARCHING';
$lang['deref_o_2']   = 'LDAP_DEREF_FINDING';
$lang['deref_o_3']   = 'LDAP_DEREF_ALWAYS';

$lang['referrals_o_-1'] = 'use default';
$lang['referrals_o_0']  = 'don\'t follow referrals';
$lang['referrals_o_1']  = 'follow referrals';

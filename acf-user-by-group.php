<?php
/**
 * Plugin Name: ACF User by Group
 * Plugin URI: https://github.com/Nessworthy/acf-user-by-group
 * Description: Allows admins to filter Advanced Custom Field's field groups by a user's group from the Groups plugin.
 * Version: 1.0
 * Author: Sean Nessworthy
 * Author URI: http://nessworthy.me
 * License: GPL2
 */

/*  Copyright 2014 Sean Nessworthy (email : sean@nessworthy.me)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// No meddling kids.
defined( 'ABSPATH' ) or die();

/**
 * The core function for the ACF User by Group plugin.
 */
function acf_user_by_group()
{

	// Pre-core check.
	if( !acf_user_by_group_hey_annie_are_you_okay() )
	{
		return false;
	}

	// Add the ACF tule type.
	add_filter('acf/location/rule_types', 'acf_user_by_group_add_rule_type', 10, 1);

	// Add the Groups to the ACF rule type location.
	add_filter('acf/location/rule_values/user_by_group', 'acf_user_by_group_add_rule_type_options', 10, 1);

	// Add the rule match check for ACF field placement pages.
	add_filter('acf/location/rule_match/user_by_group', 'acf_user_by_group_rule_match', 10, 3);

}

add_action( 'init', 'acf_user_by_group', 1 );


/**
 * Adds the "User Group" choice for ACF rule types.
 * 
 * @param  array $choices The array of filter choices available to users for ACF field groups.
 * @return array          The modified choices filter with the user group entry.
 */
function acf_user_by_group_add_rule_type( $choices )
{

	$category_key = __("Other",'acf');

	/**
	 * Filter the rule type category that the user group option should fall under.
	 *
	 * @param string $category_key The category string.
	 */
	$category_key = apply_filters( 'acf_user_by_group/rule_type_category', $category_key );

	if( !isset( $choices[ $category_key ] ) ) 
	{
		$choices[ $category_key ] = array();
	}
	
	$user_group_option_name = __( 'User Group', 'acf_user_by_group' );

	/**
	 * Filter the option name for the user group.
	 * 
	 * @param string $user_group_option_name The option name.
	 */
	$user_group_option_name = apply_filters( 'acf_user_by_group/rule_type_option_name', $user_group_option_name );

	$choices[ $category_key ][ 'user_by_group' ] = $user_group_option_name;

	return $choices;

}


/**
 * Fetch all Groups groups and add them as choices for the ACF rule type.
 * If you would like to hook into this and change the choices,
 * use 'acf/location/rule_values/user_by_group' with a priority greater than 10.
 * 
 * @param  array $choices The choices to have available.
 * @return array          The modified choices array with all groups.
 */
function acf_user_by_group_add_rule_type_options( $choices ) {

	// Add the exception 'any'.
	$choices[ 'any' ] = __( 'Any', 'acf_user_by_group' );

	// Fetch all groups.
	$groups = Groups_Group::get_groups();

	// Add all groups into the array of choices.
	if( is_array( $groups ) && count( $groups ) > 0 )
	{
		foreach( $groups as $group )
		{

			$choices[ $group->group_id ] = $group->name;

		}

	}

	return $choices;

}


/**
 * Checks if the user is in
 * 
 * @param  boolean $match   Whether to show the fields or not.
 * @param  array   $rule    Details of the rule, contains the operator, value, etc.
 * @param  array   $options Details of the current page. Contains the user ID.
 * @return [type]          [description]
 */
function acf_user_by_group_rule_match( $match, $rule, $options )
{

	$user_id  = $options[ 'ef_user' ];
	$group_id = $rule[ 'value' ];
	$operator = $rule[ 'operator' ];

	$user_in_group = false;
	$return = $match;

	// No use in checking if we're not editing a user
	if(!$user_id)
	{
		return false;
	}

	if( $group_id === 'any' )
	{

		// Check if a user is in any groups at all.
		$user = new Groups_User( $user_id );
		$user_group_ids = $user->group_ids;

		if( is_array( $user_group_ids ) && count( $user_group_ids ) > 0 )
		{
			$user_in_group = true;
		}

	}
	elseif( Groups_User_Group::read( $user_id, $group_id ) ) 
	{
		$user_in_group = true;
	}

	// The operator will modify the output.

	if( $operator === '==' )
	{
		$return = $user_in_group;
	}
	elseif ( $operator === '!=' )
	{
		$return = !$user_in_group;
	}

	return $return;

}


/**
 * Plugin setup checks.
 * 
 * @return boolean True if you can use this plugin, false if not.
 */
function acf_user_by_group_hey_annie_are_you_okay()
{

	// No need for this plugin if you're not in the admin area.
	if( !is_admin() ) 
	{
		return false;
	}

	/* There's no point in this plugin if you don't have the groups and ACF plugins.
	   We need to include the plugins file to have access to is_plugin_active.
	   Due to how early this plugin fires. */
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$plugin_acf = 'advanced-custom-fields/acf.php';
	$plugin_groups = 'groups/groups.php';

	if( !is_plugin_active( $plugin_acf ) || !is_plugin_active( $plugin_groups ) )
	{
		add_action('admin_notices', 'acf_user_by_group_nag_about_plugins');
		return false;
	}

	return true;

}


/**
 * Fired on admin notices, will display a notice to the user
 * telling them that this plugin is useless without ACF and groups.
 */
function acf_user_by_group_nag_about_plugins()
{

	$acf_url = 'http://www.itthinx.com/plugins/groups/';
	$groups_url = 'http://www.advancedcustomfields.com/';
	?>
	<div class="error">
	    <p><?php sprintf( _e( 'Hate to be a bother, but the <b>ACF User By Group</b> plugin requires the <a target="_blank" href="%s">Advanced Custom Fields</a> and <a target="_blank" href="%s">Groups</a> plugins to be of any use.', 'acf_user_by_group' ), esc_url( $acf_url ), esc_url( $groups_url ) ); ?></p>
	</div>
	<?php

}
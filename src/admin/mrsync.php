<?php
require ('includes/application_top.php');
$action = (isset($_GET['action']) ? $_GET['action'] : '');
$db_installed = (isset($_GET['db_installed']) ? $_GET['db_installed'] : '');

// Verify if we have configuration group for MR, if not, require installation before proceeding
$config_query = 'SELECT * FROM '. TABLE_CONFIGURATION_GROUP .' WHERE configuration_group_title = "MailRelay Sync" ';
$config = $db->Execute($config_query);

if ( $config->recordCount() == 0 )
{
	$need_installation = true;
	
	if ( $action == 'install_database' )
	{
		// Install configuration parameters on database
		$insert_array = array(
			'configuration_group_title' => 'MailRelay Sync',
			'configuration_group_description' => 'MailRelay Subscribers Sync',
			'visible' => 0
		);
		$insert = zen_db_perform( TABLE_CONFIGURATION_GROUP , $insert_array );
		
		$id = zen_db_insert_id();
		
		$config_values = array(
			array(
				'configuration_title' => 'Api URL',
				'configuration_key' => 'MRSYNC_URL',
				'configuration_description' => 'MR Api Url',
				'configuration_group_id' => $id
			),
			array(
				'configuration_title' => 'Api Username',
				'configuration_key' => 'MRSYNC_USERNAME',
				'configuration_description' => 'MR Api Username',
				'configuration_group_id' => $id
			),
			array(
				'configuration_title' => 'Api Password',
				'configuration_key' => 'MRSYNC_PASSWORD',
				'configuration_description' => 'MR Api Password',
				'configuration_group_id' => $id
			),
			array(
				'configuration_title' => 'Auto Sync Subscriber',
				'configuration_key' => 'MRSYNC_AUTOSYNC',
				'configuration_description' => 'Automatically sync subscribers new subscribers with MR.',
				'configuration_group_id' => $id
			),
			array(
				'configuration_title' => 'Sync Group',
				'configuration_key' => 'MRSYNC_GROUP',
				'configuration_description' => 'MR group to sync with.',
				'configuration_group_id' => $id
			),
		);
		
		foreach ( $config_values as $insert_array )
		{
			$insert = zen_db_perform( TABLE_CONFIGURATION , $insert_array );
		}
		
		header( 'Location: mrsync.php?db_installed=true' );
		die();
	}
}
else
{
	$need_installation = false;
	
	// Instance MRSync class
	require( DIR_WS_CLASSES .'mrsync.php' );
	$mr_sync = new MRSync();

	// Check if user is saving
	if ( $action == 'save' && $_POST )
	{
		$data = $_POST['mrsync'];
		
		$data['MRSYNC_URL'] = 'http://'. $data['MRSYNC_URL']  .'.ip-zone.com';
		
		// Try to login with provided data
		$login = $mr_sync->login( $data['MRSYNC_URL'] , $data['MRSYNC_USERNAME'] , $data['MRSYNC_PASSWORD'] );
		
		if ( $login )
		{
			// Check if we have to sync now
			if ( $data['MRSYNC_NOW'] == 'on' )
			{
				$sync = $mr_sync->syncAllCustomers( $data['MRSYNC_GROUP'] );
			}
			
			if ( !isset( $sync ) || $sync )
			{
				// Remove this element, no need to save it
				unset( $data['MRSYNC_NOW'] );
				
				// Save configuration paramaters
				foreach ( $data as $key => $value )
				{
					$db->Execute( 'UPDATE '. TABLE_CONFIGURATION .' SET configuration_value = "'. zen_db_prepare_input( $value ) .'" WHERE configuration_key = "'. zen_db_prepare_input( $key ) .'"' );
				}
			}
		}
	
		$errors = $mr_sync->getErrors();
	}
	
	if ( empty( $data ) )
	{
		$data = array(
			'MRSYNC_URL' => MRSYNC_URL,
			'MRSYNC_USERNAME' => MRSYNC_USERNAME,
			'MRSYNC_PASSWORD' => MRSYNC_PASSWORD,
			'MRSYNC_GROUP' => MRSYNC_GROUP,
			'MRSYNC_AUTOSYNC' => MRSYNC_AUTOSYNC,
		);
	}
}


?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php
echo HTML_PARAMS;
?>>
<head>
<meta http-equiv="Content-Type"
	content="text/html; charset=<?php
	echo CHARSET;
	?>">
<title><?php
echo TITLE;
?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css"
	href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
</head>
<body onLoad="init()">
<!-- header //-->
<?php
require (DIR_WS_INCLUDES . 'header.php');
?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
	<tr>
		<!-- body_text //-->
		<td width="100%" valign="top">
		<table border="0" width="100%" cellspacing="0" cellpadding="2">
			<tr>
				<td>
				<table border="0" width="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td class="pageHeading"><?php
						echo HEADING_TITLE;
						?></td>
						<td class="pageHeading" align="right"><?php
						echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT);
						?></td>
					</tr>
					<?php
					
					// Verify if we still need to install configuration parameters
					if ( $need_installation )
					{
						?>
						<tr>
							<td align="center" width="100%">
								<p><?php echo TEXT_NOT_INSTALLED ?></p>
								<p>&nbsp;</p>
							</td>
						</tr>
						<tr>
							<td align="center" width="100%">
								<?php echo zen_draw_form("create_table", FILENAME_MRSYNC, 'action=install_database' ); ?>
									<input type="submit" value="<?php echo TEXT_INSTALL ?>" />
								</form>
							</td>
						</tr>
						<?php
					}
					else
					{
						if ( is_array( $errors ) && !empty( $errors ) )
						{
							echo '<tr><td>';
							echo '<p>'. TEXT_FORM_ERRORS .'</p>';
							echo '<ul>';
							foreach ( $errors as $error )
							{
								echo '<li>'. $error .'</li>';
							}
							echo '</ul>';
							echo '</td></tr>';
						}
						
						if ( !empty( $data['MRSYNC_URL'] ) && !empty( $data['MRSYNC_USERNAME'] ) && !empty( $data['MRSYNC_PASSWORD'] ) )
						{
							$mr_sync->login( $data['MRSYNC_URL'] , $data['MRSYNC_USERNAME'] , $data['MRSYNC_PASSWORD'] );
							$groups = $mr_sync->getGroups();
						}
						
						?>
						<tr>
							<td width="100%">
								<?php
								if ( $db_installed )
								{
									echo '<p>'. TEXT_INSTALLED .'<p>';	
								}
								
								if ( $sync )
								{
									echo '<p>'. TEXT_SYNCED .'</p>';
								}
								?>
							
								<?php echo zen_draw_form('save', FILENAME_MRSYNC, 'action=save', 'post' ); ?>
									<table border="0" width="100%" cellspacing="0" cellpadding="2">
										<tr>
											<td class="infoBoxContent">
												<?php echo TEXT_URL ?>:
												<br />
												<?php echo zen_draw_input_field( 'mrsync[MRSYNC_URL]' , substr( $data['MRSYNC_URL'] , 7 , strpos( $data['MRSYNC_URL'] , '.ip-zone.com' ) - 7 ) ) ?>.ip-zone.com
											</td>
										</tr>
										<tr>
											<td class="infoBoxContent">
												<?php echo TEXT_USERNAME ?>:
												<br />
												<?php echo zen_draw_input_field( 'mrsync[MRSYNC_USERNAME]' , $data['MRSYNC_USERNAME'] ) ?>
											</td>
										</tr>
										<tr>
											<td class="infoBoxContent">
												<?php echo TEXT_PASSWORD ?>:
												<br />
												<?php echo zen_draw_password_field( 'mrsync[MRSYNC_PASSWORD]' , $data['MRSYNC_PASSWORD'] ) ?>
											</td>
										</tr>
										
										<?php
										// Verify if we were able to login and get groups, showing sync options below
										if ( $groups )
										{
											?>
											<tr>
												<td class="infoBoxContent">
													<?php echo zen_draw_checkbox_field( 'mrsync[MRSYNC_AUTOSYNC]' , '' , $data['MRSYNC_AUTOSYNC'] ) ?>
													<?php echo TEXT_AUTOSYNC ?>:
												</td>
											</tr>
											<tr>
												<td class="infoBoxContent">
													<?php echo TEXT_GROUP ?>:
													<br />

													<?php echo zen_draw_pull_down_menu( 'mrsync[MRSYNC_GROUP]' , $groups , $data['MRSYNC_GROUP'] ) ?>
												</td>
											</tr>
											<tr>
												<td class="infoBoxContent">
													<?php echo zen_draw_checkbox_field( 'mrsync[MRSYNC_NOW]' , '' , $data['MRSYNC_NOW'] ) ?>
													<b><?php echo TEXT_SYNCNOW ?></b>
												</td>
											</tr>
											<?php
										}
										?>
										
										<tr>
											<td class="infoBoxContent" align="center">
											<?php echo  zen_image_submit('button_save.gif', IMAGE_SAVE) ?>
											</td>
										</tr>
									</table>
								</form>
							</td>
						</tr>
						<?php
					}
					?>
				</table>
				</td>
			</tr>
		</table>
		</td>
		<!-- body_text_eof //-->
	</tr>
</table>
<!-- body_eof //-->
<!-- footer //-->
<?php
require (DIR_WS_INCLUDES . 'footer.php');
?>
<!-- footer_eof //-->
<br>

</body>
</html>
<?php
require (DIR_WS_INCLUDES . 'application_bottom.php');
?>

<?php



	/**
 	 Plugin Name: specialPress FinanceADS
	 Plugin URI: http://specialpress.de
	 Description: Get loan rates via XML and display a loan list 
	 Version: 1.0.0
	 Date: 2016/05/31
	 Author: Ralf Fuhrmann
	 Author URI: http://naranili.de
	 */


	
    
error_reporting(E_ALL);
ini_set('display_errors', 1);



	if( !isset( $_SESSION ) )
		session_start();
		
		

	/**
	 * start the class
	 */
	class SpFinanceADS
	{



		var $feedUrl = "http://data.financeads.net/getxmldata.php";
		var $feedCat = "loans";
		var $feedKey = "6d8db42fa9febf39ed786f7661332c4d";
		var $feedUid = "19132";



		/**
		 * construct
		 */
		function SpFinanceADS() 
		{
			


			/**
			 * create datatables on plugin activation
			 */
			register_activation_hook( __FILE__, array( &$this, 'spfads_register_activation_hook' ) );
			register_deactivation_hook( __FILE__, array( &$this, 'spfads_register_deactivation_hook' ) );


			/**
			 * add the action to get the XML
			 */
			add_action( 'spfads_cron_event', array( &$this, 'GetFinanceAdsXML' ) );
			

			/**
			 * add the shortcode
			 */
			add_shortcode( 'financeads', array( &$this, 'shortcode_financeads' ) );
			
			
		}	
			
		
		
		/**
		 * display the form and the result table
		 */	
		function shortcode_financeads( $atts ) 
		{



			global $wpdb;
			
			
			/**
			 * check if we have an amount
			 */
			if( empty( $_POST[ 'fa_rk_kreditbetrag' ] ))
				$_POST[ 'fa_rk_kreditbetrag' ] = 10000;
				

				
			/** 
			 * check if we have a productid
			 */
			if( empty( $atts[ 'productid' ] ) )
				return( 'Please define a productid' );
				
				
			/**
			 * query the database to get the product data
			 */
			$product = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}spfads_product WHERE productId = {$atts[ 'productid' ]}", ARRAY_A );
			
			if( !$product )
				return( 'Product not found at Database' );


			/**
			 * check if we need a template
			 */
			if( empty( $atts[ 'ntpl' ] ) )
				$atts[ 'ntpl' ] = 'default';
				
				
				
			/**
			 * we only load the scripts if we need it
			 */
			wp_enqueue_script( 'financeads', plugins_url( '/js/sp_FinanceADS.js' , __FILE__ ), array( 'jquery' ) );			
			wp_enqueue_style( 'financeads', plugins_url( '/templates/' . $atts[ 'ntpl' ] . '/sp_FinanceADS.css' , __FILE__ ) );			

				
				
			/**
			 * if we have a pageid attribute we load the permalink
			 */
			 if( $atts[ 'pageid' ] )
				$product[ 'permalink' ] = get_permalink( $atts[ 'pageid' ] );
				
				
			/**
			 * query the database to get the product details
			 */
			$details = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}spfads_productdetails WHERE productId = {$atts[ 'productid' ]} AND ( amount_min <= {$_POST[ 'fa_rk_kreditbetrag' ]} AND amount_max >= {$_POST[ 'fa_rk_kreditbetrag' ]} ) ORDER BY period_min DESC, interest_eff ASC LIMIT 9999", ARRAY_A );
			
			
			
			/**
			 * clear the loop vars
			 */
			$details_array = array();
			$period_min = 9999;
			$period_max = 0;



			/**
			 * loop thru the details and collect the data
			 * to get the min and max period
			 */
			foreach( $details AS $detail )
			{
			
			
				if( $detail[ 'period_min' ] < $period_min )
					$period_min = $detail[ 'period_min' ];
				
				
				if( $detail[ 'period_max' ] > $period_max )
					$period_max = $detail[ 'period_max' ];


			}
			

			
			/**
			 * setup the details array with blank values
			 */
			for( $i = $period_max; $i >= $period_min; $i = $i - 12 )
			{
			
					$details_array[ $i ][ 'interest_eff_min' ] = 99;
					$details_array[ $i ][ 'interest_nom_min' ] = 99;
					$details_array[ $i ][ 'interest_eff_max' ] = 0;
					$details_array[ $i ][ 'interest_nom_max' ] = 0;
					
			}


			
			/**
			 * loop thru the details and collect the data
			 * to get the min and max interest for the given periods
			 */
			foreach( $details AS $detail )
			{
			
				
				for( $i = $detail[ 'period_max' ]; $i >= $detail[ 'period_min' ]; $i = $i - 12 )
				{
				
				
				
					/**
					 * get the min and max interests
					 */
					if( $detail[ 'interest_eff' ] < $details_array[ $i ][ 'interest_eff_min' ] )
						$details_array[ $i ][ 'interest_eff_min' ] = $detail[ 'interest_eff' ];
			
					if( $detail[ 'interest_eff' ] > $details_array[ $i ][ 'interest_eff_max' ] )
						$details_array[ $i ][ 'interest_eff_max' ] = $detail[ 'interest_eff' ];
					
					if( $detail[ 'interest_nom' ] < $details_array[ $i ][ 'interest_nom_min' ] )
						$details_array[ $i ][ 'interest_nom_min' ] = $detail[ 'interest_nom' ];
					
					if( $detail[ 'interest_nom' ] > $details_array[ $i ][ 'interest_nom_max' ] )
						$details_array[ $i ][ 'interest_nom_max' ] = $detail[ 'interest_nom' ];


				}
																	
			}


			ob_start();
			require( plugin_dir_path( __FILE__ ) . '/templates/' . $atts[ 'ntpl' ] . '/sp_FinanceADS.tpl' ); 
			return ob_get_clean();			
			
			
		}
		
			
			


		/**
		 * create the datatable and the schedules on activation
		 */
		function spfads_register_activation_hook()
		{

    
			global $wpdb;
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );


			/**
			 * table with the product data
			 */
			$table_name = $wpdb->prefix . 'spfads_product'; 
			$wpdb->query( "DROP table IF EXISTS {$table_name}" );
        
			$sql = "    CREATE TABLE {$table_name} 
							(
								ID int(11) NOT NULL AUTO_INCREMENT,
								bank VARCHAR(255) NOT NULL,
								bankId int(11) NOT NULL,
								productId int(11) NOT NULL,
								productname VARCHAR(255) NOT NULL,
								logo VARCHAR(255) NOT NULL,
								url VARCHAR(255) NOT NULL,
								legaldata TEXT,
								PRIMARY KEY (ID)
			);";

			dbDelta($sql);



			/**
			 * table with the productdetails data
			 */
			$table_name = $wpdb->prefix . 'spfads_productdetails'; 
			$wpdb->query( "DROP table IF EXISTS {$table_name}" );
        
			$sql = "    CREATE TABLE {$table_name} 
							(
								ID int(11) NOT NULL AUTO_INCREMENT,
								bankId int(11) NOT NULL,
								productId int(11) NOT NULL,
								period_min int(11) NOT NULL,
								period_max int(11) NOT NULL,
								amount_min int(11) NOT NULL,
								amount_max int(11) NOT NULL,
								interest_eff double NOT NULL,
								interest_nom double NOT NULL,
								cost_add double NOT NULL,
								solvency_lev tinyint NOT NULL,
								solvency_dep tinyint NOT NULL,
								PRIMARY KEY (ID)
			);";

			dbDelta($sql);



			/**
			 * add the schedules to retrieve the XML from FinanceADS
			 */
			$schedule = wp_get_schedule( 'spfads_cron_event' );	
			if( !$schedule )
				wp_schedule_event( time(), 'hourly', 'spfads_cron_event' );	
			

			self::GetFinanceAdsXML();


		}			



		/**
		 * Delete the schedules on deactivation
		 */
		function spfads_register_deactivation_hook()
		{


			wp_clear_scheduled_hook( 'spfads_cron_event' );
			

		}
		


		/**
		 * get the loan data from financeADS and store the data into the database
		 */
		function GetFinanceAdsXML()
		{
		
		
			global $wpdb;
		

			/**
			 * build the feedSource
			 */
			$feedSource = $this->feedUrl;
			$feedSource .= '?w=' . get_option( 'financeAds_wfID' );
			$feedSource .= '&key=' . $this->feedKey;
			$feedSource .= '&cat=' . $this->feedCat;


			/**
			 * get the data with CURL
			 */
			$ch = curl_init( $feedSource );

			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Content-Type: text/xml" ) );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

			$result = curl_exec( $ch );
			
			curl_close( $ch );
			
			
			/**
			 * try to read the XML
			 */
			 
			try
			{


				/**
				 * process the XML
				 */
				$xml_results = new SimpleXMLElement( $result );
				
				/**
				 * truncate the data tables
				 */
				$query = "TRUNCATE TABLE {$wpdb->prefix}spfads_product";
				$wpdb->query( $query );
				$query = "TRUNCATE TABLE {$wpdb->prefix}spfads_productdetails";
				$wpdb->query( $query );



				/**
				 * loop thru the XML array
				 */
				foreach( $xml_results AS $product )
				{
			
			
					$product_attributes = $product->attributes();
				

					/**
					 * insert the data into the product table
					 */
					$wpdb->insert(
					
						$wpdb->prefix . 'spfads_product',
					
						array(
							'ID' => NULL,
							'bank' => '' . $product->bank,
							'bankId' => $product->bankid,
							'productId' => $product_attributes->id,
							'productname' => $product->productname,
							'logo' => $product->logo,
							'url' => $product->url,
							'legaldata' => $product->legaldata
							),
						array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
					
					);
					
						
				
					foreach( $product->productdetails AS $details )
					{
				
				
						/**
						 * insert the data into the productdetails table
						 */
						$wpdb->insert(
						
							$wpdb->prefix . 'spfads_productdetails',
					
							array(
								'ID' => NULL,
								'bankId' => $product->bankid,
								'productId' => $product_attributes->id,
								'period_min' => $details->period_min,
								'period_max' => $details->period_max,
								'amount_min' => $details->amount_min,
								'amount_max' => $details->amount_max,
								'interest_eff' => $details->interest_eff,
								'interest_nom' => $details->interest_nom,
								'cost_add' => $details->cost_add,
								'solvency_lev' => $details->solvency_lev,
								'solvency_dep' => $details->solvency_dep
								),
							array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%d' )
					
						);
					
				
					}
				

				}
			

			} catch ( Exception $e ) 
			{
		
				/**
				 * ignore the error
				 */
				return;	
		
		
			}
			
			
		}



	}


	/* instance class */
	$SpFinanceADS = new SpFinanceADS();
	
	
	
	
?>

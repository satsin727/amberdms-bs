<?php
/*
	include/services/inc_service_cdr.php

	Provides various functions and classes for handling CDR billing and the
	configuration of CDR items.
*/



/*
	CLASS cdr_rate_table

	Functions for querying and managing CDR rate tables.
*/
class cdr_rate_table
{
	var $id;		// rate table ID
	var $data;		// rate table data



	/*
		verify_id

		Check that the supplied rate table ID is valid.

		Results
		0	Failure to find the ID
		1	Success - service exists
	*/

	function verify_id()
	{
		log_debug("cdr_rate_table", "Executing verify_id()");

		if ($this->id)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `cdr_rate_tables` WHERE id='". $this->id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				return 1;
			}
		}

		return 0;

	} // end of verify_id

	

	/*
		verify_rate_table_name

		Verify that the supplied rate table name has not been taken.

		Results
		0	Failure - name in use
		1	Success - name is available
	*/

	function verify_rate_table_name()
	{
		log_write("debug", "cdr_rate_table", "Executing verify_rate_table_name()");

		$sql_obj			= New sql_query;
		$sql_obj->string		= "SELECT id FROM `cdr_rate_tables` WHERE rate_table_name='". $this->data["rate_table_name"] ."' ";

		if ($this->id)
			$sql_obj->string	.= " AND id!='". $this->id ."'";

		$sql_obj->string		.= " LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 0;
		}
		
		return 1;

	} // end of verify_rate_table_name


	/*
		check_delete_lock

		Checks if the customer is safe to delete or not

		Results
		0	Unlocked
		1	Locked
	*/

	function check_delete_lock()
	{
		log_debug("inc_customers", "Executing check_delete_lock()");


		// makes sure not in use by any services
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM services WHERE id_rate_table='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 1;
		}


		// unlocked
		return 0;

	}  // end of check_delete_lock




	/*
		load_data

		Load the rate table into the $this->data array.

		Returns
		0	failure
		1	success
	*/
	function load_data()
	{
		log_debug("cdr_rate_table", "Executing load_data()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id_vendor, id_usage_mode, rate_table_name, rate_table_description FROM cdr_rate_tables WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			// fetch basic service data
			$sql_obj->fetch_array();

			$this->data["id_vendor"]		= $sql_obj->data[0]["id_vendor"];
			$this->data["id_usage_mode"]		= $sql_obj->data[0]["id_usage_mode"];
			$this->data["rate_table_name"]		= $sql_obj->data[0]["rate_table_name"];
			$this->data["rate_table_description"]	= $sql_obj->data[0]["rate_table_description"];

			// fetch strings
			$this->data["id_usage_mode_string"]	= sql_get_singlevalue("SELECT name as value FROM cdr_rate_usage_modes WHERE id='". $this->data["id_usage_mode"] ."' LIMIT 1");

			return 1;
		}

		// failure
		return 0;

	} // end of load_data



	/*
		action_create
	
		Create a rate table based on the data in $this->data

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_create()
	{
		log_write("debug", "cdr_rate_table", "Executing action_create()");


		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			Create CDR Rate Table
		*/
		$sql_obj->string	= "INSERT INTO `cdr_rate_tables` (rate_table_name) VALUES ('". $this->data["rate_table_name"]. "')";
		$sql_obj->execute();

		$this->id = $sql_obj->fetch_insert_id();



		/*
			Create DEFAULT & LOCAL rate items.
		*/

		$sql_obj->string	= "INSERT INTO `cdr_rate_tables_values` (id_rate_table, rate_prefix, rate_billgroup) VALUES ('". $this->id ."', 'DEFAULT', '1')";
		$sql_obj->execute();

		$sql_obj->string	= "INSERT INTO `cdr_rate_tables_values` (id_rate_table, rate_prefix, rate_billgroup) VALUES ('". $this->id ."', 'LOCAL', '1')";
		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_rate_table", "An error occured when attemping to create a new rate table.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			return $this->id;
		}


	} // end of action_create




	/*
		action_update

		Update the details for the selected rate table based on the data in $this->data. If no ID is provided,
		it will first call the action_create function to add a new rate table.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_update()
	{
		log_write("debug", "cdr_rate_table", "Executing action_update()");

		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			If no ID supplied, create a new rate table first
		*/
		if (!$this->id)
		{
			$mode = "create";

			if (!$this->action_create())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}



		/*
			Update Rate Table Details
		*/

		$sql_obj->string	= "UPDATE `cdr_rate_tables` SET "
						."id_vendor='". $this->data["id_vendor"] ."', "
						."id_usage_mode='". $this->data["id_usage_mode"] ."', "
						."rate_table_name='". $this->data["rate_table_name"] ."', "
						."rate_table_description='". $this->data["rate_table_description"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		

		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_rate_table", "An error occured when updating rate table details.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "cdr_rate_table", "Rate table details successfully updated.");
			}
			else
			{
				log_write("notification", "cdr_rate_table", "Rate table successfully created.");
			}
			
			return $this->id;
		}

	} // end of action_update



	/*
		action_delete

		Deletes the selected rate table.

		Results
		0	Failure
		1	Success
	*/
	function action_delete()
	{
		log_write("debug", "cdr_rate_table", "Executing action_delete()");


		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			delete rate table
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "DELETE FROM `cdr_rate_tables` WHERE id='". $this->id ."'";
		$sql_obj->execute();


		/*
			delete rate table items
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "DELETE FROM `cdr_rate_tables_values` WHERE id_rate_table='". $this->id ."'";
		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_rate_table", "An error occured when deleting the selected rate table, no changes have been made.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "cdr_rate_table", "Rate table details successfully deleted.");

			return 1;
		}

	} // end of action_delete



} // end of class cdr_rate_table	




/*
	CLASS cdr_rate_table_rates

	Functions for querying and managing rates inside of a rate table.
*/
class cdr_rate_table_rates extends cdr_rate_table
{
	var $data_rate;		// data for a single rate to manipulate
	var $id_rate;		// ID of a single rate to manipulate



	/*
		verify_id_rate

		Check that the supplied table rate item id is valid ($this->id_rate) and also
		verifies that it belongs to the selected rate table, or selects it if it is not.

		Results
		0	Failure to find the ID
		1	Success - service exists
	*/

	function verify_id_rate()
	{
		log_debug("cdr_rate_table_rates", "Executing verify_id_rate()");

		if ($this->id_rate)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id, id_rate_table FROM `cdr_rate_tables_values` WHERE id='". $this->id_rate ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();


				if ($this->id)
				{
					if ($sql_obj->data[0]["id_rate_table"] == $this->id)
					{
						return 1;
					}
					else
					{
						log_write("error", "cdr_rate_table_rates", "The selected rate (". $this->id_rate .") does not match the selected rate table (". $this->id .")");
						return 0;
					}
				}
				else
				{
					$this->id = $sql_obj->data[0]["id_rate_table"];

					return 1;
				}

			}
		}

		return 0;

	} // end of verify_id_rate



	/*
		verify_rate_prefix

		Verify that the supplied rate prefix is not already in use.
		
		Results
		0	Failure - prefix in use
		1	Success - prefix is available
	*/

	function verify_rate_prefix()
	{
		log_write("debug", "cdr_rate_table", "Executing verify_rate_prefix()");

		$sql_obj			= New sql_query;
		$sql_obj->string		= "SELECT id FROM `cdr_rate_tables_values` WHERE rate_prefix='". $this->data_rate["rate_prefix"] ."' AND id_rate_table='". $this->id. "'";

		if ($this->id_rate)
			$sql_obj->string	.= " AND id!='". $this->id_rate ."'";

		$sql_obj->string		.= " LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 0;
		}
		
		return 1;

	} // end of verify_rate_prefix





	/*
		load_data_rate_all

		Load the rate table into the $this->data["rates"] array.

		Returns
		0	failure
		1	success
	*/
	function load_data_rate_all()
	{
		log_debug("cdr_rate_table", "Executing load_data_rate_all()");

		// Fetch Billgroups
		$sql_billgroup_obj		= New sql_query;
		$sql_billgroup_obj->string	= "SELECT id, billgroup_name FROM cdr_rate_billgroups";
		$sql_billgroup_obj->execute();
		$sql_billgroup_obj->fetch_array();

		$billgroup = array();

		foreach ($sql_billgroup_obj->data as $data_row)
		{
			$billgroup[ $data_row["id"] ] = $data_row["billgroup_name"];
		}

		unset($sql_billgroup_obj);
		

		// fetch rates
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id, rate_prefix, rate_description, rate_billgroup, rate_price_sale, rate_price_cost FROM cdr_rate_tables_values WHERE id_rate_table='". $this->id ."'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			foreach ($sql_obj->data as $data_rates)
			{
				$this->data["rates"][ $data_rates["rate_prefix"] ]["id_rate"]			= $data_rates["id"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_prefix"]		= $data_rates["rate_prefix"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_description"]		= $data_rates["rate_description"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup"]		= $data_rates["rate_billgroup"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup_string"]	= $billgroup[ $data_rates["rate_billgroup"] ];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_price_sale"]		= $data_rates["rate_price_sale"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_price_cost"]		= $data_rates["rate_price_cost"];

			}

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_rate_all



	/*
		load_data_rate

		Load a single data rate value into $this->data_rate

		Returns
		0	failure
		1	success
	*/
	function load_data_rate()
	{
		log_debug("cdr_rate_table", "Executing load_data_rate()");


		// fetch rates
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id, rate_prefix, rate_description, rate_billgroup, rate_price_sale, rate_price_cost FROM cdr_rate_tables_values WHERE id_rate_table='". $this->id ."' AND id='". $this->id_rate ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			$this->data_rate["rate_prefix"]		= $sql_obj->data[0]["rate_prefix"];
			$this->data_rate["rate_description"]	= $sql_obj->data[0]["rate_description"];
			$this->data_rate["rate_billgroup"]	= $sql_obj->data[0]["rate_billgroup"];
			$this->data_rate["rate_price_sale"]	= $sql_obj->data[0]["rate_price_sale"];
			$this->data_rate["rate_price_cost"]	= $sql_obj->data[0]["rate_price_cost"];

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_rates

	

	/*
		action_rate_create

		Create a new rate item for the selected rate table.

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_rate_create()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_create()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO `cdr_rate_tables_values` (id_rate_table, rate_prefix) VALUES ('". $this->id ."', '". $this->data_rate["rate_prefix"]. "')";
		$sql_obj->execute();

		$this->id_rate = $sql_obj->fetch_insert_id();

		return $this->id_rate;

	} // end of action_rate_create




	/*
		action_rate_update

		Update the details for the selected rate based on the data in $this->data_rate. If no ID is provided,
		it will first call the action_rate_create function to add a new rate.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_rate_update()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_update()");

		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			If no ID supplied, create a new rate first
		*/
		if (!$this->id_rate)
		{
			$mode = "create";

			if (!$this->action_rate_create())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}



		/*
			Update Rate Details
		*/

		$sql_obj->string	= "UPDATE `cdr_rate_tables_values` SET "
						."rate_prefix='". $this->data_rate["rate_prefix"] ."', "
						."rate_description='". $this->data_rate["rate_description"] ."', "
						."rate_billgroup='". $this->data_rate["rate_billgroup"] ."', "
						."rate_price_sale='". $this->data_rate["rate_price_sale"] ."', "
						."rate_price_cost='". $this->data_rate["rate_price_cost"] ."' "
						."WHERE id='". $this->id_rate ."' LIMIT 1";
		$sql_obj->execute();

		

		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_rate_table", "An error occured when updating rate item details.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "cdr_rate_table_rates", "Rate item successfully updated.");
			}
			else
			{
				log_write("notification", "cdr_rate_table_rates", "Rate item successfully created.");
			}
			
			return $this->id_rate;
		}

	} // end of action_rate_update



	/*
		action_rate_delete

		Deletes the selected rate from it's rate table.

		Results
		0	Failure
		1	Success
	*/
	function action_rate_delete()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_delete()");

		if ($this->data_rate["rate_prefix"] == "DEFAULT")
		{
			log_write("error", "cdr_rate_table_rates", "Unable to delete the DEFAULT prefix, this is required incase calls don't match any other prefix.");
			return 0;
		}

		if ($this->data_rate["rate_prefix"] == "LOCAL")
		{
			log_write("error", "cdr_rate_table_rates", "Unable to delete the LOCAL prefix, this prefix is required in order to bill for local calling.");
			return 0;
		}


		$sql_obj		= New sql_query;
		$sql_obj->string	= "DELETE FROM `cdr_rate_tables_values` WHERE id='". $this->id_rate ."' LIMIT 1";
	
		if ($sql_obj->execute())
		{
			log_write("notification", "cdr_rate_table_rates", "Requested rate item has been deleted.");
			return 1;
		}
		else
		{
			log_write("error", "cdr_rate_table_rates", "An error occured whilst attempting to delete the requested rate item (". $this->id_rate .") for rate table (". $this->id .")");
			return 0;
		}

	} // end of action_rate_delete




	/*
		calculate_prefix

		Use the rates loaded in $this->data to determine what the prefix of the supplied number is

		Fields
		num		Phone Number


		Returns
		-1		no prefix found
		#		prefix
	*/

	function calculate_prefix( $num )
	{
		log_write("debug", "cdr_rate_table_rates", "Executing calculate_prefix($num)");


		/*
			Loop though knocking off one number at a time till we match a prefix value.
		*/

		while ( $num )
		{
			if (!empty($this->data["rates"][ $num ]["id_rate"]))
			{
				log_write("debug", "cdr_rate_table_rates", "Returning prefix $num");

				return $num;
			}

			$num = substr($num, 0, -1);
		}


		log_write("debug", "cdr_rate_table_rates", "Unable to match to any known prefix, returning DEFAULT prefix");

		return 'DEFAULT';


	} // calculate_prefix



	/*
		calculate_charges

		Uses the rates loaded in $this->data to determine the cost of a call.

		Fields
		seconds		Number of BILLALBE seconds
		src		Source phone number
		dst		Destination phone number
		local_prefix	Local prefix - either integer or a string (optional) 
		DDI array	Array of customer's DDIs (optional)

		Returns
		-1			Failure
		array			Associative Array
			['price']	# Price (float, no formatting, tax-exclusive)
			['billgroup']	Bill Group
	*/

	function calculate_charges($seconds, $src, $dst, $local_prefix = NULL, $ddi_array = array())
	{
		log_write("debug", "cdr_rate_table_rates", "Executing calculate_charges($seconds, $src, $dst, $local_prefix)");


		/*
			The logic here is very important, this function determines how much to charge calls for, any
			mistakes could result in under or overcharges to customers.

			First, determine the prefix to charge with:
			* Use calculate_prefix to fetch the ID of the prefix that the src number belongs to
			* Do the same for the dest number.
			* Is it a local call (ie: same prefix or within local_prefix zone) If so, fetch LOCAL rate
			* Unknown? Then fetch the DEFAULT rate.
			* Otherwise, use the rate that was supplied
			
			To determine how much time to charge:
			* Fetch the method of the rate time - is it 60 seconds + per second or something else?
			* Based on that method, determine the time to charge for.

			Finally:
			* Apply the prefix rate agains the charge rate
		*/


		/*
			Fetch prefix numbers
		*/

		$prefix_src	= $this->calculate_prefix($src);
		$prefix_dst	= $this->calculate_prefix($dst);


		/*
			Check for Local Prefix

			Local prefix can be handled in two ways:
			1. Integer	:: Local prefix is integer prefix, eg "64123"
			2. String	:: Local prefix is a region/destination string, eg "Wellington"

			We should cache this information to reduce the amount of processing, since this function is called
			repeatedly, but at the same time, take care to make sure we do not cache across different services/
			customers.

			We cache in the local object, so that the cache only applies to one object and is cleared upon object
			destruction, as well as allowing multiple objects to exist during a single execution without conflict.
		*/

		if (!empty($local_prefix))
		{
			if (!empty($this->cache[ $local_prefix ]))
			{
				// return local prefix from cache
				$local_prefix = $this->cache[ $local_prefix ];
			}
			else
			{
				// determine local prefix(es)
				if (preg_match("/^[0-9]*$/", $local_prefix))
				{
					// Integer Prefix - format as array only

					$this->cache[ $local_prefix ]	= array($local_prefix);
					$local_prefix			= array($local_prefix);
				}
				else
				{
					// String/Region Prefix - we need to lookup against the rate tables and fetch all prefixes
					// that make up this region and create a numerical prefix array

					log_write("debug", "inc_service_usage_cdr", "Resolving prefixes for local prefix $local_prefix");


					$local_prefix_tmp = array();

					foreach ($this->data["rates"] as $rate)
					{
						if (isset($rate["rate_description"]))
						{
							if ($rate["rate_description"] == $local_prefix)
							{
								$local_prefix_tmp[] = $rate["rate_prefix"];
							}
						}
					}


					// update cache
					$this->cache[ $local_prefix ]	= $local_prefix_tmp;

					// adjust prefix for use.
					$local_prefix			= $local_prefix_tmp;

				} // end if string/region

			} // end of from cache

		} // end if local prefix defined



		/*
			Determine charging rate
		*/

		$billed = 0;


		if (!$billed && $prefix_src == $prefix_dst)
		{
			// inner prefix calling, this is always going to be local
			$rate_minute	= $this->data["rates"]["LOCAL"]["rate_price_sale"];
			$billgroup	= "1"; // local

			$billed = 1;
		}


		if (in_array($dst, $ddi_array))
		{
			// customer has called one of their own DDI numbers
			//
			// NOTE: this excludes DDI numbers that the customer might have on other services, it only applies to DDIs in the currently
			//	 loaded service.

			switch ($GLOBALS["config"]["SERVICE_CDR_BILLSELF"])
			{
				case "free":
					$rate_minute	= "0.00";
				break;

				case "local":
					$rate_minute	= $this->data["rates"]["LOCAL"]["rate_price_sale"];
					$billgroup	= "1"; // local
				break;

				case "regular":
				default:
					$rate_minute	= $this->data["rates"][ $prefix_dst ]["rate_price_sale"];
					$billgroup	= $this->data["rates"][ $prefix_dst ]["rate_billgroup"];
				break;
			}

			$billed = 1;
		}


		if (!$billed && is_array($local_prefix))
		{
			// local prefix has been set, if any apply, then bill
			foreach ($local_prefix as $local_prefix_single)
			{

				if (preg_match("/^$local_prefix_single/", $prefix_dst))
				{
					// local prefix - numbers might not be both in the same exact prefix, but
					// the destination belongs to the local prefix option

					$rate_minute	= $this->data["rates"]["LOCAL"]["rate_price_sale"];
					$billgroup	= "1";

					$billed = 1;
				}
			}
		}

		if (!$billed)
		{
			// default: fall back to standard prefix matching and billing
			$rate_minute	= $this->data["rates"][ $prefix_dst ]["rate_price_sale"];
			$billgroup	= $this->data["rates"][ $prefix_dst ]["rate_billgroup"];
		}


		$rate_second		= $rate_minute / 60;



		/*
			Determine charges
		*/

		switch ($this->data["id_usage_mode_string"])
		{
			case "per_minute":
				//
				// charge for whole minutes only
				// round up to the nearest minute.
				//
				
				$seconds	= ceil($seconds/60)*60; 

				$charges	= $seconds * $rate_second;
				
			break;

			case "per_second":
				//
				// charge for the number of seconds
				//

				$charges	= $seconds * $rate_second;
			break;

			case "first_min_then_per_second":
			default:
				//
				// charge for a minimum of 1 minute and then
				// per-second after that.
				//

				if ($seconds < 60)
				{
					$seconds = 60;
				}
			
				$charges	= $seconds * $rate_second;
			break;
		}


		log_write("debug", "cdr_rate_table_rates", "Call of $seconds seconds from $src to $dst cost $charges for billgroup $billgroup");

		$return_array			= array();
		$return_array["price"]		= $charges;
		$return_array["billgroup"]	= $billgroup;

		return $return_array;
	}


} // end of cdr_rate_table_rates





/*
	CLASS cdr_rate_table_rates_override

	Functions for querying and retreving override for CDR rates.
*/
class cdr_rate_table_rates_override extends cdr_rate_table_rates
{
	var $option_type;		// option type category ("customer" or "service")
	var $option_type_id;		// option_type id
	var $option_type_serviceid;	// option service id (used by customer overrides)

	var $id_rate_override;	// id of a particular rate to manipulate


	/*
		verify_id_override

		Verify the supplied options IDs in order to fetch the override
		data.

		Results
		0	Failure to verify
		1	Success
	*/
	function verify_id_override()
	{
		log_write("debug", "cdr_rate_table", "Executing verify_id_override()");



		if ($this->option_type == "service")
		{
			/*
				Verify and return rate table ID
			*/
			$obj_sql		= New sql_query;
			$obj_sql->string	= "SELECT id, id_rate_table as id_service FROM services WHERE id='". $this->option_type_id ."' LIMIT 1";
			$obj_sql->execute();

			if ($obj_sql->num_rows())
			{
				$obj_sql->fetch_array();

				$id_rate_table = $obj_sql->data[0]["id_rate_table"];

			}
			else
			{
				log_write("error", "cdr_rate_table", "Unable to find ID $option_type_id in services");
				return 0;
			}
		}
		elseif ($this->option_type == "customer")
		{
			/*
				verify customer-services ID by fetching rate table ID for the service.
			*/


			// fetch service ID
			$obj_sql		= New sql_query;
			$obj_sql->string	= "SELECT serviceid as id_service FROM services_customers WHERE id='". $this->option_type_id ."' LIMIT 1";
			$obj_sql->execute();

			if ($obj_sql->num_rows())
			{
				$obj_sql->fetch_array();

				$this->option_type_serviceid = $obj_sql->data[0]["id_service"];
			}
			else
			{
				log_write("error", "cdr_rate_table", "Unable to find service with ID of $option_type_id in services_customers");
				return 0;
			}


			// fetch service rate table ID
			$obj_sql		= New sql_query;
			$obj_sql->string	= "SELECT id, id_rate_table FROM services WHERE id='". $this->option_type_serviceid ."' LIMIT 1";
			$obj_sql->execute();

			if ($obj_sql->num_rows())
			{
				$obj_sql->fetch_array();

				$id_rate_table = $obj_sql->data[0]["id_rate_table"];
			}
			else
			{
				log_write("error", "cdr_rate_table", "Unable to find ID $option_type_id in services - perhaps service is not a phone service?");
				return 0;
			}
	
		}
		else
		{
			log_write("warning", "cdr_rate_table", "No such option type $option_type");
			return 0;
		}



		/*
			Verify or select rate table ID
		*/

		if (!$this->id)
		{
			// no rate table selected, select the one that belongs to the service ID
			$this->id = $id_rate_table;

			log_write("debug", "cdr_rate_table", "Selecting rate table ID \"". $this->id ."\"");

			return 1;
		}
		else
		{
			// verify the service ID against the currently selected rate_table
			if ($this->id != $id_rate_table)
			{
				log_write("error", "cdr_rate_table", "Service options returned id_rate_table of $id_rate_table but currently selected rate table is ". $this->id ."");
				return 0;
			}
			else
			{
				// valid match
				return 1;
			}
			
		}

		return 0;
	}



	/*
		verify_id_rate_override

		Returns
		0	Failure - ID not found
		1	Success
	*/

	function verify_id_rate_override()
	{
		log_debug("cdr_rate_table_rates", "Executing verify_id_rate_override()");

		if ($this->id_rate_override)
		{
			// TODO: verify option_type and option_type_id here?

			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `cdr_rate_tables_overrides` WHERE id='". $this->id_rate_override ."' AND option_type='". $this->option_type ."' AND option_type_id='". $this->option_type_id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				return 1;

			}
		}

		return 0;

	} // end of verify_id_rate_override



	/*
		verify_rate_prefix_override

		Verify that the supplied rate prefix is not already in use in the override table
		
		Results
		0	Failure - prefix in use
		1	Success - prefix is available
	*/

	function verify_rate_prefix_override()
	{
		log_write("debug", "cdr_rate_table", "Executing verify_rate_prefix_override()");

		$sql_obj			= New sql_query;
		$sql_obj->string		= "SELECT id FROM `cdr_rate_tables_overrides` WHERE rate_prefix='". $this->data_rate["rate_prefix"] ."' AND option_type='". $this->option_type ."' AND option_type_id='". $this->option_type_id ."'";

		if ($this->id_rate_override)
			$sql_obj->string	.= " AND id!='". $this->id_rate_override ."'";

		$sql_obj->string		.= " LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 0;
		}
		
		return 1;

	} // end of verify_rate_prefix_override





	/*
		load_data_rate_all_override

		Load the rate table into the $this->data["rates"] array. If you want to get the full rate table including the non-overridden values
		then do a load_data_rate_all followed by load_data_rate_all_override

		Returns
		0	failure
		1	success
	*/
	function load_data_rate_all_override()
	{
		log_debug("cdr_rate_table", "Executing load_data_rate_all_overrride()");


		/*
			Fetch Billgroups
		*/

		$sql_billgroup_obj		= New sql_query;
		$sql_billgroup_obj->string	= "SELECT id, billgroup_name FROM cdr_rate_billgroups";
		$sql_billgroup_obj->execute();
		$sql_billgroup_obj->fetch_array();

		$billgroup = array();

		foreach ($sql_billgroup_obj->data as $data_row)
		{
			$billgroup[ $data_row["id"] ] = $data_row["billgroup_name"];
		}

		unset($sql_billgroup_obj);
		


		/*
			If this is a customer override, we first need to load service-level overrides to ensure
			that the customer gets overrides from both services and customers.
		*/
		if ($this->option_type == "customer")
		{
			// fetch rates
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id, rate_prefix, rate_description, rate_billgroup, rate_price_sale FROM cdr_rate_tables_overrides WHERE option_type='service' AND option_type_id='". $this->option_type_serviceid ."'";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();

				foreach ($sql_obj->data as $data_rates)
				{
					$this->data["rates"][ $data_rates["rate_prefix"] ]["id_rate_override"]		= $data_rates["id"];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_prefix"]		= $data_rates["rate_prefix"];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_description"]		= $data_rates["rate_description"];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup"]		= $data_rates["rate_billgroup"];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup_string"]	= $billgroup[ $data_rates["rate_billgroup"] ];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_price_sale"]		= $data_rates["rate_price_sale"];
					$this->data["rates"][ $data_rates["rate_prefix"] ]["option_type"]		= 'service';
				}
			}
		}


		// fetch rates
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id, rate_prefix, rate_description, rate_billgroup, rate_price_sale FROM cdr_rate_tables_overrides WHERE option_type='". $this->option_type ."' AND option_type_id='". $this->option_type_id ."'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			foreach ($sql_obj->data as $data_rates)
			{
				$this->data["rates"][ $data_rates["rate_prefix"] ]["id_rate_override"]		= $data_rates["id"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_prefix"]		= $data_rates["rate_prefix"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_description"]		= $data_rates["rate_description"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup"]		= $data_rates["rate_billgroup"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_billgroup_string"]	= $billgroup[ $data_rates["rate_billgroup"] ];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["rate_price_sale"]		= $data_rates["rate_price_sale"];
				$this->data["rates"][ $data_rates["rate_prefix"] ]["option_type"]		= $this->option_type;
			}

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_rate_all_override



	/*
		load_data_rate_override

		Load a single data rate value into $this->data_rate

		Returns
		0	failure
		1	success
	*/
	function load_data_rate_override()
	{
		log_debug("cdr_rate_table", "Executing load_data_rate_override()");


		// fetch rates
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id, rate_prefix, rate_description, rate_billgroup, rate_price_sale FROM cdr_rate_tables_overrides WHERE id='". $this->id_rate_override ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			$this->data_rate["rate_prefix"]		= $sql_obj->data[0]["rate_prefix"];
			$this->data_rate["rate_description"]	= $sql_obj->data[0]["rate_description"];
			$this->data_rate["rate_billgroup"]	= $sql_obj->data[0]["rate_billgroup"];
			$this->data_rate["rate_price_sale"]	= $sql_obj->data[0]["rate_price_sale"];

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_rate_override

	

	/*
		action_rate_create_override

		Create a new data rate override

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_rate_create_override()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_create_override()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO `cdr_rate_tables_overrides` (option_type, option_type_id, rate_prefix) VALUES ('". $this->option_type ."', '". $this->option_type_id ."', '". $this->data_rate["rate_prefix"]. "')";
		$sql_obj->execute();

		$this->id_rate_override = $sql_obj->fetch_insert_id();

		return $this->id_rate_override;

	} // end of action_rate_create_override




	/*
		action_rate_update_override

		Update the details for the selected rate based on the data in $this->data_rate. If no ID is provided,
		it will first call the action_rate_create function to add a new rate.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_rate_update_override()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_update_override()");

		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			If no ID supplied, create a new rate first
		*/
		if (!$this->id_rate_override)
		{
			$mode = "create";

			if (!$this->action_rate_create_override())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}



		/*
			Update Rate Details
		*/

		$sql_obj->string	= "UPDATE `cdr_rate_tables_overrides` SET "
						."rate_prefix='". $this->data_rate["rate_prefix"] ."', "
						."rate_description='". $this->data_rate["rate_description"] ."', "
						."rate_billgroup='". $this->data_rate["rate_billgroup"] ."', "
						."rate_price_sale='". $this->data_rate["rate_price_sale"] ."' "
						."WHERE id='". $this->id_rate_override ."' LIMIT 1";
		$sql_obj->execute();

		

		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_rate_table", "An error occured when updating rate override details.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "cdr_rate_table_rates", "Rate override successfully updated.");
			}
			else
			{
				log_write("notification", "cdr_rate_table_rates", "Rate override successfully created.");
			}
			
			return $this->id_rate;
		}

	} // end of action_rate_update_override



	/*
		action_rate_delete_override

		Deletes the selected rate from it's rate table.

		Results
		0	Failure
		1	Success
	*/
	function action_rate_delete_override()
	{
		log_write("debug", "cdr_rate_table_rates", "Executing action_rate_delete_override()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "DELETE FROM `cdr_rate_tables_overrides` WHERE id='". $this->id_rate_override ."' LIMIT 1";
	
		if ($sql_obj->execute())
		{
			log_write("notification", "cdr_rate_table_rates", "Requested rate override has been deleted.");
			return 1;
		}
		else
		{
			log_write("error", "cdr_rate_table_rates", "An error occured whilst attempting to delete the requested rate override (". $this->id_rate_override .").");
			return 0;
		}

	} // end of action_rate_delete_override



} // end of cdr_rate_table_rates_override







/*
	CLASS: service_usage_cdr

	Functions for querying and calculating call costs for service invoicing.
*/
class service_usage_cdr extends service_usage
{

	var $data_ddi;		// contains DDI information loaded by load_data_di
	var $data_local;	// contains local calling region information


	/*
		load_data_ddi

		Fetches an array of all the customer's DDIs into the $this->data value and returns the number of DDIs assigned.

		Returns
		0		No DDIs / An error occured
		#		Number of DDIs belonging to the customer.
	*/

	function load_data_ddi()
	{
		log_write("debug", "service_usage_cdr", "Executing load_data_ddi()");


		// fetch all the DDIs for this service-customer
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT ddi_start, ddi_finish, local_prefix FROM services_customers_ddi WHERE id_service_customer='". $this->id_service_customer ."'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			$this->data_ddi		= array();
			$this->data_local	= array();


			// work out DDI ranges where nessacary.
			foreach ($sql_obj->data as $data)
			{
				// ddis
				if ($data["ddi_start"] == $data["ddi_finish"])
				{
					// single DDI, very easy
					$this->data_ddi[] = $data["ddi_start"];

					// local calling zone
					$this->data_local[ $data["ddi_start"] ] = $data["local_prefix"];
				}
				else
				{
					// multiple DDIs, go and generate all the DDIs inbetween
					for ($i=$data["ddi_start"]; $i <= $data["ddi_finish"]; $i++)
					{
						// add number
						$this->data_ddi[]	= $i;
				
						// local calling zone
						$this->data_local[$i]	= $data["local_prefix"];
					}
				}

			}

			// return total number of DDIs
			$total	= count($this->data_ddi);

			log_write("debug", "service_usage_cdr", "Customer has ". $total ." DDIs on their service");

			return $total;

		}
		else
		{
			log_write("warning", "service_usage_cdr", "There are no DDIs assigned to id_service_customer ". $this->id_service_customer ."");
			return 0;
		}

	} // end of load_data_ddi




	/*
		load_data_trunks

		Returns
		0		No trunks / An error occured
		#		Number of trunks belonging to the customer
	*/


	/*
		fetch_usage_ddi

		// TODO: move code out of invoicegen to here

	*/


	/*
		fetch_usage_trunks

		// TODO: move code out of invoicegen to here
	*/



	/*
		fetch_usage_calls

		Pulls call records from the CDR databases, prices and returns costs per DDI for the specified
		time period.

		Returns
		0		failure
		1		success
	*/
	function fetch_usage_calls()
	{
		log_write("debug", "service_usage_cdr", "Executing fetch_usage_calls()");


		/* 
			Load Call Pricing (including overrides for this customer)
		*/

		$obj_cdr_rate_table			= New cdr_rate_table_rates_override;

		$obj_cdr_rate_table->option_type	= "customer";
		$obj_cdr_rate_table->option_type_id	= $this->id_service_customer;

		$obj_cdr_rate_table->verify_id_override();

		$obj_cdr_rate_table->load_data();

		$obj_cdr_rate_table->load_data_rate_all();
		$obj_cdr_rate_table->load_data_rate_all_override();


		/*
			We need a list of the DDIs for this customer.
		*/
		if (!$this->data_ddi)
		{
			$this->load_data_ddi();
		}


		/*
			Query Call Records
		*/

		if ($GLOBALS["config"]["SERVICE_CDR_MODE"] == "internal")
		{
			log_write("debug", "service_usage_cdr", "Fetching call records from internal ABS database..");


			/*
				Whilst for large datasets, Amberdms recommends using an external CDR database, it may be
				desirable to use the internal ABS call data database for record billing, such as when importing from
				another system via the API.

				when using the internal database, the service_usage_records table is used:

				date		Date of Call
				price		Price of call
				usage1		Source DDI
				usage2		Destination DDI
				usage3		Billable call seconds


				Note that we don't need to loop through the DDIs here, since anyone using the internal database has already matched the DDIs to
				the id_service_customer via the API usage upload.


				The way the internal database is used will vary depending on the information provided - if a price is already set, this
				price will be used for the total call cost.

				If no price is set, the costs will be looked up in the rate table.

				This behaviour allows charged call rates to be imported from another platform and not having to be re-calculated against
				ABS's own rate table.
			*/


			// calculate end date to be the first date of the next period - failure to do so would mean we would
			// miss the last day of the billing period in the query;

			$date_start		= $this->date_start;

			$tmp_date		= explode("-", $this->date_end);
			$date_end		= date("Y-m-d", mktime(0,0,0,$tmp_date[1], ($tmp_date[2] +1), $tmp_date[0]));


			/*
				Fetch Data

				Just a simple query here, however if this is a TOLLFREE service, then we need to reverse
				the query to charge for inbound calls rather than outbound.
			*/

			log_write("debug", "service_usage_cdr", "Fetching usage records FOR $ddi FROM $date_start TO $date_end");

			$obj_cdr_sql		= New sql_query;

			if ($this->obj_service->data["typeid_string"] == "phone_tollfree")
			{

				log_write("debug", "service_usage_cdr", "Billing for tollfree service on $ddi");

				// NOTE! for toll-free services, we reverse src and dst for reverse billing calculations
				$obj_cdr_sql->string = "SELECT id, date, price, usage1 as dst, usage2 as src, usage3 as billsec, billgroup FROM service_usage_records WHERE id_service_customer='". $this->id_service_customer ."' AND date >= '$date_start' AND date < '$date_end'";
				$obj_cdr_sql->execute();
			}
			else
			{
				$obj_cdr_sql->string = "SELECT id, date, price, usage1 as src, usage2 as dst, usage3 as billsec, billgroup FROM service_usage_records WHERE id_service_customer='". $this->id_service_customer ."' AND date >= '$date_start' AND date < '$date_end'";
				$obj_cdr_sql->execute();
			}


			/*
				Calculate costs of calls
			*/
			if ($obj_cdr_sql->num_rows())
			{
				$obj_cdr_sql->fetch_array();

				foreach ($obj_cdr_sql->data as $data_cdr)
				{
					// determine price
					if ($data_cdr["price"] != "0.00")
					{
						// a price has already been set - make use of that
						$charges		= array();
						$charges["price"]	= $data_cdr["price"];
						$charges["billgroup"]	= $data_cdr["billgroup"];
					}
					else
					{
						$charges = $obj_cdr_rate_table->calculate_charges($data_cdr["billsec"], $data_cdr["src"], $data_cdr["dst"], $this->data_local[ $data_cdr["src"] ], $this->data_ddi);

						// update the charges in the records
						$sql_obj			= New sql_query;
						$sql_obj->string		= "UPDATE service_usage_records SET price='". $charges["price"] ."', billgroup='". $charges['billgroup'] ."' WHERE id='". $data_cdr["id"] ."'";
						$sql_obj->execute();
					}

					// add to structure - we use the SRC as the DDI
					$this->data[ $data_cdr["src"] ][ $charges["billgroup"] ]["charges"]	+= $charges["price"];

					// TODO: this won't catch issues where the DDIs configured don't match the SRC DDI (although it should always!)
				}
			}

		} // end of internal DB
		else
		{
			/*
				Connect to External SQL database
			*/

			// fetch all calls for that DDI from the DB for the selected period
			$obj_cdr_db_sql = New sql_query;

			if (!$obj_cdr_db_sql->session_init("mysql", $GLOBALS["config"]["SERVICE_CDR_DB_HOST"], $GLOBALS["config"]["SERVICE_CDR_DB_NAME"], $GLOBALS["config"]["SERVICE_CDR_DB_USERNAME"], $GLOBALS["config"]["SERVICE_CDR_DB_PASSWORD"]))
			{
				return 0;
			}


			/*
				We fetch the call records by looping through all the DDIs for this customer, fetching all the records
				for those DDIs and then calculating the cost for each call
			*/

			foreach ($this->data_ddi as $ddi)
			{
				// calculate end date to be the first date of the next period - failure to do so would mean we would
				// miss the last day of the billing period in the query;

				$date_start		= $this->date_start;

				$tmp_date		= explode("-", $this->date_end);
				$date_end		= date("Y-m-d", mktime(0,0,0,$tmp_date[1], ($tmp_date[2] +1), $tmp_date[0]));


				/*
					Fetch Data

					Just a simple query here, however if this is a TOLLFREE service, then we need to reverse
					the query to charge for inbound calls rather than outbound.

				*/
				log_write("debug", "service_usage_cdr", "Fetching usage records FOR $ddi FROM $date_start TO $date_end");

				if ($this->obj_service->data["typeid_string"] == "phone_tollfree")
				{
					log_write("debug", "service_usage_cdr", "Billing for tollfree service on $ddi");

					// NOTE! for toll-free services, we reverse src and dst for reverse billing calculations
					$obj_cdr_db_sql->string		= "SELECT calldate, billsec, dst as src, src as dst FROM cdr WHERE disposition='ANSWERED' AND dst='$ddi' AND calldate >= '$date_start' AND calldate < '$date_end'";
					$obj_cdr_db_sql->execute();
				}
				else
				{
					$obj_cdr_db_sql->string		= "SELECT calldate, billsec, src, dst FROM cdr WHERE disposition='ANSWERED' AND src='$ddi' AND calldate >= '$date_start' AND calldate < '$date_end'";
					$obj_cdr_db_sql->execute();
				}


				/*
					Calculate costs of calls
				*/

				if (!isset($this->data[ $ddi ][ $charges["billgroup"] ]["charges"]))
				{
					$this->data[ $ddi ][ $charges["billgroup"] ]["charges"] = 0;
				}

				if ($obj_cdr_db_sql->num_rows())
				{
					$obj_cdr_db_sql->fetch_array();

					foreach ($obj_cdr_db_sql->data as $data_cdr)
					{
						// determine price
						$charges			= $obj_cdr_rate_table->calculate_charges($data_cdr["billsec"], $data_cdr["src"], $data_cdr["dst"], $this->data_local[ $ddi ], $this->data_ddi);

						// create local usage record for record keeping purposes
						$sql_obj			= New sql_query;
						$sql_obj->string		= "INSERT INTO service_usage_records (id_service_customer, date, price, usage1, usage2, usage3, billgroup) VALUES ('". $this->id_service_customer ."', '". $data_cdr["calldate"] ."', '". $charges["price"] ."', '". $data_cdr["src"] ."', '". $data_cdr["dst"] ."', '". $data_cdr["billsec"] ."', '". $charges["billgroup"] ."')";
						$sql_obj->execute();

						// add to structure
						$this->data[ $ddi ][ $charges["billgroup"] ]["charges"]	+= $charges["price"];
					}
				}

			} // end of DDI loop



			/*
				Disconnect from database
			*/

			$obj_cdr_db_sql->session_terminate();

		} // end of external data source

	} // end of fetch_usage_calls


} // end of class: service_usage_cdr




/*
	CLASS: cdr_customer_service_ddi

	Functions for managing DDI values for customers as well as being able to query
	a specific DDI to discover what customer it belongs to.
*/

class cdr_customer_service_ddi
{
	var $id;			// ID of the DDI record
	var $data;			// DDI record data/values to change.

	var $id_customer;		//
	var $id_service_customer;	//



	/*
		verify_id

		Verify that the supplied ID is valid and fetch the customer and service-customer IDs that go along with it.

		Results
		0	Failure to find the ID
		1	Success
	*/

	function verify_id()
	{
		log_debug("cdr_customer_services_ddi", "Executing verify_id_service_ddi()");

		if ($this->id)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id_service_customer, services_customers.customerid as id_customer FROM `services_customers_ddi` LEFT JOIN services_customers ON services_customers.id = services_customers_ddi.id_service_customer WHERE services_customers_ddi.id='". $this->id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();


				// verify id_service_customer
				if ($this->id_service_customer)
				{
					if ($sql_obj->data[0]["id_service_customer"] == $this->id_service_customer)
					{
						log_write("debug", "customers_services_ddi", "The selected service-customer matches the DDI");
					}
					else
					{
						log_write("error", "customers_services_ddi", "The seleced service-customer (". $this->id_service_customer .") does not match the selected customer (". $this->id .").");
						return 0;
					}
				}
				else
				{
					$this->id_service_customer = $sql_obj->data[0]["id_service_customer"];

					log_write("debug", "customers_services_ddi", "Setting id_service_customer to ". $this->id_service_customer ."");
				}


				// verify customer ID
				if ($this->id_customer)
				{
					if ($sql_obj->data[0]["id_customer"] == $this->id_customer)
					{
						log_write("debug", "customers_services_ddi", "The selected DDI belongs to the correct customer and service-customer mapping");
						return 1;
					}
					else
					{
						log_write("error", "customers_services_ddi", "The seelcted DDI does not belong to the selected customer ". $this->id ."");
						return 0;
					}

				}
				else
				{
					$this->id_customer = $sql_obj->data[0]["id_customer"];

					log_write("debug", "customers_services_ddi", "Setting id_customer to ". $this->id ."");
					return 1;
				}

			}
		}

		return 0;

	} // end of verify_id_service_ddi



	/*
		verify_unique_ddi

		Verifies that the supplied DDI information is not already used by any other DDI

		Results
		0	Failure - ddi is assigned to another customer
		1	Success - ddi is available
	*/

	function verify_unique_ddi()
	{
		log_debug("cdr_customer_services_ddi", "Executing verify_unique_ddi()");
/*
		TODO: write me

		$sql_obj			= New sql_query;
		$sql_obj->string		= "SELECT id FROM `services_customers_ddi` WHERE r='". $this->data["code_customer"] ."' ";

		if ($this->id)
			$sql_obj->string	.= " AND id!='". $this->id ."'";

		$sql_obj->string		.= " LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 0;
		}
*/
		return 1;

	} // end of verify_unique_ddi



	/*
		load_data

		Load the DDI data

		Results
		0	Failure
		1	Success
	*/
	function load_data()
	{
		log_write("debug", "cdr_customer_services_ddi", "Executing load_data_ddi()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT ddi_start, ddi_finish, local_prefix, description FROM services_customers_ddi WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			$this->data = $sql_obj->data[0];

			return 1;
		}

		return 0;

	} // end of load_data_ddi
	


	/*
		action_create

		Create a new DDI record based on the data in $this->data

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_create()
	{
		log_debug("cdr_customer_services_ddi", "Executing action_create()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO `services_customers_ddi` (id_service_customer) VALUES ('". $this->id_service_customer . "')";
		$sql_obj->execute();

		$this->id = $sql_obj->fetch_insert_id();

		return $this->id;

	} // end of action_create




	/*
		action_update

		Updates DDI action

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_update()
	{
		log_debug("cdr_customer_services_ddi", "Executing action_update()");

		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			If no ID supplied, create a new DDI first
		*/
		if (!$this->id)
		{
			$mode = "create";

			if (!$this->action_create())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}



		/*
			Update DDI value
		*/

		$sql_obj->string	= "UPDATE `services_customers_ddi` SET "
						."ddi_start='". $this->data["ddi_start"] ."', "
						."ddi_finish='". $this->data["ddi_finish"] ."', "
						."local_prefix='". $this->data["local_prefix"] ."', "
						."description='". $this->data["description"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		
		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_customers_services_ddi", "An error occured when updating customer DDI records.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "cdr_customers_services_ddi", "Customer DDI records successfully updated.");
			}
			else
			{
				log_write("notification", "cdr_customers_service_ddi", "Customer DDI records successfully created.");
			}
			
			return $this->id;
		}

	} // end of action_update



	/*
		action_delete

		Deletes a DDI

		Results
		0	failure
		1	success
	*/
	function action_delete()
	{
		log_debug("cdr_customers_services_ddi", "Executing action_delete()");


		/*
			Start Transaction
		*/

		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			Delete DDI
		*/
			
		$sql_obj->string	= "DELETE FROM services_customers_ddi WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();


		/*
			Commit
		*/
		
		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "cdr_customers_services_ddi", "An error occured whilst trying to delete the DDI.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "cdr_customers_services_ddi", "DDI has been successfully deleted.");

			return 1;
		}
	}


} // end of class: customer_services_ddi



/*
	CLASS: cdr_csv

	Functions for exporting cdr output in csv

	This class is more a collection of specific functions for CDR output to CSV

*/

class cdr_csv
{
	var $data;			// data from this cdr_csv export

	var $id_customer;		//
	var $id_service_customer;	//
	var $period_start;		//
	var $period_end;		//

	var $required_params;		//
	var $csv_body_fields;		//


	function cdr_csv($options = false) {
		$this->required_params 	= array('id_customer', 'id_service_customer', 'period_start', 'period_end');
		$this->csv_body_fields  = array('code_customer', 'number_src', 'number_dst', 'date_date', 'date_time', 'billable_seconds', 'price', 'qualifier');

		// available, but unused: rate_billgroup

		if($options) {
			return $this->setOptions($options);
		}
	}

	function setOptions($options) {
		foreach($options as $k => $v) {
			if(in_array($k, $this->required_params)) {
				$this->$k = $v;
			}
		}
	}

	function hasParams() {
		$retval = false;

		if(is_array($this->required_params)) {
			$retval = true;
			foreach($this->required_params as $k) {
				if(!isset($this->$k)) {
					log_write("error", "cdr_csv", "Requred parameter for cdr_csv output: " . $k . " was not specified");
					$retval = false;
				}
			}
		}	
		return $retval;
	}

	function isDate($value) {

		$expression = "/^[0-9]*-[0-9]*-[0-9]*$/";

		if (preg_match($expression, $value)) {
			$value = addslashes($value);

			return $value;
		}

		log_write("error", "cdr_csv", "Date $value is invalid");
		
		return false;
	}

	function getQuery() {
		// what do we need
		if(!$this->hasParams()) {
			return false;
		}

		$sql = "SELECT 
				date as date_date, 
				'00:00:00' as date_time,
				cdr_rate_billgroups.billgroup_name as rate_billgroup, 
				usage1 as number_src, 
				usage2 as number_dst, 
				usage3 as billable_seconds, 
				price, 
				'ANSWERED' AS qualifier 
			FROM service_usage_records 
			LEFT JOIN cdr_rate_billgroups 
				ON cdr_rate_billgroups.id = service_usage_records.billgroup 
			WHERE 
				id_service_customer = '" . $this->id_service_customer . "' 
				AND date >= '" . $this->isDate($this->period_start) . "' 
				AND date <= '" . $this->isDate($this->period_end) . "' 
			ORDER BY 
				date ASC , 
				rate_billgroup ASC , 
				number_src ASC , 
				number_dst ASC 
		";

		return $sql;

	}

	function runQuery($sql) {

		$this->sql_obj		= New sql_query;
		$this->sql_obj->string	= $sql;
		$this->sql_obj->execute();
		$this->sql_obj->fetch_array();

	}

	function getData() { 
		return $this->runQuery($this->getQuery());
	}

	function makeCSV() {

		// have a go at getting the data if its not there
		if(!is_object($this->sql_obj)) {
			$this->getData();
		}

		// fail if data not found
		if(!is_object($this->sql_obj)) {
			log_write("error", "cdr_csv", __FUNCTION__ . " called but no data present, has the query been performed?");
			return false;
		}


		// fetch customer code information
		$customer_data = sql_get_singlerow("SELECT code_customer, reseller_customer, reseller_id FROM customers WHERE id='". $this->id_customer ."' LIMIT 1");

		if (!$customer_data)
		{
			log_write("error", "inc_services_cdr", "No customer with ID ". $this->id_customer ." returned!");
		}
		else
		{
			$code_customer = $customer_data["code_customer"];

			// is the customer a member of a reseller? If so, we need the code of the reseller to also use.
			if ($customer_data["reseller_customer"] == "customer_of_reseller")
			{
				$code_master = sql_get_singlevalue("SELECT code_customer as value FROM customers WHERE id='". $customer_data["reseller_id"] ."'");
			}
			else
			{
				// customer is standalone, use their code as the master
				$code_master = $code_customer;
			}
		}

		// string padding is required as per the following:
		/*
	  			HEADER SECTION	
			1	HD-HEADER	Char 6	HEADER	Field contains the following text 'HEADER'
			2	HD-CUST-NUM	Num 11	Left 0 padded	Master customer account number
			3	HD-FILE-CREATE-DATE	Char 10	YYYY-MM-DD	Date the file is created
						
			BODY SECTION	
			4	BD-CUST-NBR	Num 11	Left 0 padded	Customer account number
			5	BD-ORIG-ANI	Num 15	Left 0 padded E164	Originating Party 'A'
			6	BD-TERM-NBR	Char 16	Left space padded in dialied E164 format	Calling Party 'B'
			7	BD-START-DT	Char 10	YYYY-MM-DD	Date extracted from the start date
			8	BD-START-TM	Char 8	HH.MM.SS	Time extracted from the start time
			9	BD-RATED-SECS	Num 9	Left 0 padded	Rated seconds
			10	BD-RATED-AMT	Char 10	Left 0 padded before decimal point and right 0 padded after the decimal point XXXXX.XXXX	Rated Dollar amount (excludes all taxes)
			11	BD-ANS-QUALIFIER	Char 10	As per values in description field	Possible values, ANSWERED, BUSY, FAILED, NO ANSWER
								
			TRAILER SECTION	
			12	TR-TRAILER	Char 7	TRAILER	The following text is in this field
			13	TR-REC-COUNT	Num 7	Left 0 padded	The number of records in the file, excluding the header and trailer records
		*/
		
		// header of CSV
		$this->csv[] = array('HEADER', str_pad($code_master, 11, "0", STR_PAD_LEFT), date('Y-m-d'));

		if(count($this->sql_obj->data) > 0) {
			$csv_record_count = count($this->sql_obj->data);
			foreach($this->sql_obj->data as $row) {

				// as such this only currently supports one customer account number
				$row['code_customer'] = $code_customer;

				// reset the row
				$csv_row = array();

				// loop over the csv_body_fields and apply any formating required before adding to the csv_row in the order defined
				foreach($this->csv_body_fields as $k) {

					switch($k) {
						case 'code_customer':
							$csv_row[$k] = str_pad($row[$k], 11, "0", STR_PAD_LEFT);
							break;
						case 'number_src':
							$csv_row[$k] = str_pad($row[$k], 11, "0", STR_PAD_LEFT);
							break;
						case 'number_dst':
							$csv_row[$k] = str_pad($row[$k], 15, " ", STR_PAD_LEFT);
							break;
						case 'billable_seconds':
							$csv_row[$k] = str_pad($row[$k], 9, "0", STR_PAD_LEFT);
							break;
						case 'price':
							$csv_row[$k] = trim(money_format('%=0^!#5.4i', $row[$k]));
							break;

						default;
							$csv_row[$k] = $row[$k];
						break;
					}

				}

				// add the body row
				$this->csv[] = $csv_row;
			}
		} else {
			$csv_record_count = 0;
		}

		// footer of csv
		$this->csv[] = array('TRAILER', str_pad($csv_record_count,7,"0", STR_PAD_LEFT));

	}

	function getCSV($line_ending = "\n") {

		if(!isset($this->csv)) {
			$this->makeCSV();
		}

		if(isset($this->csv)) {
			$csv = '';
			foreach($this->csv as $data) {
				$row = '';
				foreach($data as $v) {
					$row .= '"' . $v . '",';
				}
				$csv .= rtrim($row, ",") . $line_ending;
			}
			return $csv;
		} else {
                        log_write("error", "cdr_csv", "Error producing CDR CSV output due to data failure");
                        return false;
		}

	}



} // end of class: cdr_csv

?>

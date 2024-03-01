<?

if(cfr('PAYFIND')){
	
	function web_CatSelector($current){
		$query = "SELECT `id`,`category` FROM `logistis_cattypes`";
		$result='';
		$cats = array();
		$allcats = simple_queryall($query);
		
		if(!empty($allcats)){
				$cats[0] = __('No category');
			foreach($allcats as $io => $eachcat){
				$cats[$eachcat['id']] = __($eachcat['category']);
			}
				
		}
		$result = wf_Selector('catid',$cats,__('Categories'),$current,false,false);
		return($result);
	}
	
	// Returns company selection dropdown
	function web_CompanySelector($current){
		$query = "SELECT `id`,`company_name` FROM `logistis_companies`";
		$result='';
		$company = array();
		$allcompanies = simple_queryall($query);
		
		if(!empty($allcompanies)){
				$company[0] = __('None');
			foreach($allcompanies as $io => $each){
				$company[$each['id']] = __($each['company_name']);
			}				
		}
		$result = wf_Selector('company_id',$company,__('Company'),$current,false,true);
		return($result);	
	}
	
	//Returns category id assignment by exp_id request
	function get_OpCatId($opid){
		if(!empty($opid)){
			$query = "SELECT `cat_id` FROM `logistis_categories` WHERE `exp_id`=$opid";
			$result = simple_queryall($query);
			
			if(!empty($result)){
				foreach($result as $row){
					return $row['cat_id'];
				}	
			} else {
				//Category assignment does not exist, create it
				$query = "INSERT INTO `logistis_categories` (`exp_id`,`cat_id`) VALUES ('$opid','0')";
				nr_query($query);
				return 0;
			}
		}
	}
	
	//Returns assigned company by exp_id
	function get_CompanyByOpId($exp_id){
		if(!empty($exp_id)){
			$query = "SELECT `company_id` FROM `logistis_company_assignments` WHERE `exp_id`=$exp_id";
			$result = simple_queryall($query);
			
			if(!empty($result)){
				foreach($result as $row){
					return $row['company_id'];
				}	
			} else {
				//Category assignment does not exist, create it
				//$query = "INSERT INTO `logistis_company_assignments` (`exp_id`,`company_id`) VALUES ('$exp_id','0')";
				//nr_query($query);
				return 0;
			}
		}	
	}

	
	//Deletes Expense with specified ida
	//Deletes Expense category assignment with specified category id
	function DelExpense($exp_id){
		$delAdmins = array();
		$iCanDelete = false;
		$whoami = whoami();
		
		//Before deleting, we extract amount and comment in order to write this info into the log
		$query = "SELECT `date`,`summ`,`comment` FROM `expenses` WHERE `id`='$exp_id'";
		$result = simple_queryall($query);
			if(!empty($result)){
				foreach($result as $row){
					$date = $row['date'];
					$sum = $row['summ'];
					$note = $row['comment'];
				}
			}
		

		//extract delete admin logins
            if (!empty($alter['CAN_DELETE_PAYMENTS'])) {
                $delAdmins = explode(',', $alter['CAN_DELETE_PAYMENTS']);
                $delAdmins = array_flip($delAdmins);
            }
			$iCanDelete = (isset($delAdmins[$whoami])) ? true : false;
			
			//right check
			//if($iCanDelete){
				//Delecting transaction
				$delQuery = "DELETE FROM `expenses` WHERE `id`='$exp_id'";
				nr_query($delQuery);
				//Deleting category
				$delQuery = "DELETE FROM `logistis_categories` WHERE `exp_id`='$exp_id'";
				nr_query($delQuery);
				//Deleting company assignment
				$delQuery = "DELETE FROM `logistis_company_assignments` WHERE `exp_id`='$exp_id'";
				nr_query($delQuery);
				
				$log_text = ' ';
				$log_text .= 'SUM ['.$sum.'] ';
				$log_text .= 'NOTE: ]'.$note.']';
				$log_text .= ', DATE: '.$date;
				log_register("EXPENSE DELETE [" . $exp_id . "] ".$log_text);
				//rcms_redirect('?module=addcash&username=' . $login . '#cashfield');
			//} else {
            //    log_register("EXPENSE UNAUTH DELETION ATTEMPT [" . $exp_id . "]");
            //}
	}	

	/** Returns Operation edit form **/
	function web_EditopForm(){
			$opid = stripslashes($_GET['opid']);
			$query = "SELECT * FROM `expenses` WHERE `id`=$opid";
			$operation = simple_queryall($query);
			
			if(!empty($operation)){
				foreach ($operation as $eachop){
					$op_id = $eachop['id'];
					$op_admin = $eachop['user'];
					$op_summ = $eachop['summ'];
					$op_comment = $eachop['comment'];
				}
			}
			
		$query = "SELECT * FROM `logistis_opitems` WHERE `exp_id`=$opid";
		$opitems = simple_queryall($query);
		
			if(!empty($opitems)){
				//Перебираем все элементы, связанные с операцией
				foreach ($opitems as $each_opitem){
					$opitem_price = $each_opitem['price'];
					$opitem_qty = $each_opitem['qty'];
					$opitem_makeid = $each_opitem['make_id'];
					$opitem_modelid = $each_opitem['model_id'];
					$opitem_categoryid = $each_opitem['category_id'];
					$opitem_parentcatid = $each_opitem['parent_catid'];
				}
			}
		
		
		
		$inputs = wf_HiddenInput('edit','true');
		$inputs.= wf_HiddenInput('oldsum', $op_summ);
		$inputs.= wf_TextInput('admin',__('Administrator'),$op_admin, true,'10');
		$inputs.= wf_delimiter();
		$inputs.= wf_TextInput('summ',__('Sum'),$op_summ, true,'10');
		$inputs.= wf_delimiter();
		$inputs.= wf_TextArea('note',__('Comment'),$op_comment, false,'30x5');
		$inputs.= wf_delimiter();
		$inputs.= web_CatSelector(get_OpCatId($op_id));
		$inputs.= wf_delimiter();
		$inputs.= web_CompanySelector(get_CompanyByOpId($op_id));
		$inputs.= wf_delimiter();
		
		if (cfr('SWITCHESEDIT')) {
			$inputs .= wf_delimiter(0);
			$inputs .= wf_Submit('Save');
		}
		$result = wf_Form('','POST',$inputs, 'glamour');
		
		$result.= wf_delimiter();
			
			
		/** Items table **/
		
		if(!empty($opitems)){
			//Получаем категории товаров
			$query = "SELECT * FROM `logistis_opmakes`";
			$opmakes = simple_queryall($query);	

			$query = "SELECT * FROM `logistis_opmodels`";
			$opmodels = simple_queryall($query);

			$total = 0.00;
			$cells = wf_TableCell(__('Item'));
			$cells.= wf_TableCell(__('Quantity'),'10%');
			$cells.= wf_TableCell(__('Price'),'10%');
			$cells.= wf_TableCell(__('Action'),'20%');
			$rows = wf_TableRow($cells,'row3');
			foreach($opitems as $each_opitem){
				$cells = wf_TableCell($opmakes[$each_opitem['make_id']]['make']." ".$opmodels[$each_opitem['model_id']]['model']);
				$cells.= wf_TableCell($each_opitem['qty']);
				$cells.= wf_TableCell($each_opitem['price']);
				$cells.= wf_TableCell('');
				$rows.=wf_TableRow($cells);
				
				if($each_opitem['qty']>1){
					$total = $total + $each_opitem['price']*$each_opitem['qty'];
				} else {
					$total = $total + $each_opitem['price'];
				}
			}
			$cells = wf_TableCell('');
			$cells.= wf_TableCell('');
			$cells.= wf_TableCell('');
			$cells.= wf_TableCell('');
			$rows.= wf_TableRow($cells,'row2');
			
			$cells = wf_TableCell(__('Total before VAT'));
			$cells.= wf_TableCell('');
			$cells.= wf_TableCell(round($total, 2,PHP_ROUND_HALF_DOWN),'10%');
			$cells.= wf_TableCell('');
			$rows.= wf_TableRow($cells,'row1');
			
			
			$cells = wf_TableCell(__('VAT'));
			$cells.= wf_TableCell('');
			$cells.= wf_TableCell(round($total*0.19, 2,PHP_ROUND_HALF_DOWN),'10%');
			$cells.= wf_TableCell('');
			$rows.= wf_TableRow($cells,'row1');
			
			$cells = wf_TableCell(__('Total'));
			$cells.= wf_TableCell('');
			$cells.= wf_TableCell(round($total*1.19,2),'10%');
			$cells.= wf_TableCell('');
			$rows.= wf_TableRow($cells,'row1');
			
			$result.= wf_TableBody($rows,'50%','0','sortable');
		}
		
		
		
		$result.= wf_delimiter();

		$result.= wf_BackLink("?module=logistis2");
		$result.= wf_JSAlertStyled('?module=logistis_operation&expdelete='.$opid,web_delete_icon() . ' ' . __('Delete'),'This action will remove the operation forever', 'ubButton');
		
		return($result);
	}
	
	function web_OperationView(){
		
	}
	
	//Operation editing
	if(wf_CheckGet(array('opid'))){
		if(wf_CheckPost(array('edit'))){ 
			$opid = vf($_GET['opid'],3);
			$oldsum = vf($_POST['oldsum'],3);
						
			simple_update_field('expenses','summ',$_POST['summ'], "WHERE `id`='$opid'");
			simple_update_field('expenses','comment',$_POST['note'], "WHERE `id`='$opid'");
			simple_update_field('logistis_categories', 'cat_id', $_POST['catid'], "WHERE `exp_id`='$opid'");
			simple_update_field('logistis_company_assignments','company_id',$_POST['company_id'], "WHERE `exp_id` = '$opid'");
			
			$log_text = " ";
			if($oldsum!=$_POST['summ']){
				$log_text .= "OLD SUM ".$oldsum;
			}
			
			log_register('EXPENSE CHANGE [' . $opid . ']' . $log_text);
            rcms_redirect("?module=logistis_operation&opid=" . $opid);
		}
	}
	
	//Operation deletion
	if(wf_CheckGet(array('expdelete'))){
		$delexpid = vf($_GET['expdelete'],3);
		DelExpense($delexpid);
		rcms_redirect("?module=logistis2");
	}
	
	if(wf_CheckGet(array('opid'))){
		show_window(__('Edit operation'),web_EditopForm());
	}
}
?>
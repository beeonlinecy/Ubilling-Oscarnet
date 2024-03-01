<?
if(cfr('PAYFIND')){
	// Returns category selection dropdown
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
		$result = wf_Selector('catid',$cats,'',$current,false,false);
		return($result);
	}
	
	//Listings categories and subcategories as ul li list
	function web_ShowCats($parent_id){
		$query = "SELECT * FROM `logistis_cattypes` WHERE `parent_id`='$parent_id'";
		$cats = simple_queryall($query);
		
		if(!empty($cats)){
			$result = wf_tag('ul',false);
			foreach($cats as $row){		
				$result.= wf_tag('li',false);
				$result.= __($row['category']);
				$result.= wf_tag('li',true);
				$result.= web_ShowCats($row['id']);	
			}
			$result.= wf_tag('ul',true);
			
			return($result);
		}
	}
	
	function web_ShowCatSettings(){
		$cells = wf_TableCell(__('Parent category'));
		$cells.= wf_TableCell(__('Category name'));
		$cells.= wf_TableCell('','');
		$rows = wf_TableRow($cells,'row1');
		
		$cells = wf_HiddenInput('addcategory','true');
		$cells.= wf_TableCell(web_CatSelector(0));
		$cells.= wf_TableCell(wf_TextInput('catname','','',true,'20'));
		$cells.= wf_TableCell(wf_Submit(__('Add')));
		$rows.= ($cells);
		
		$result = wf_Form('','POST',wf_TableBody($rows,'30%'),'glamour');
		
		
		return $result;
	}
	
	function web_ShowCompanySettings(){
		$query = "SELECT * FROM `logistis_companies`";
		$companies = simple_queryall($query);
		
		$cells = wf_TableCell(__('Company name'));
		$rows = wf_TableRow($cells,'row1');
		
		
		
		if(!empty($companies)){
			foreach($companies as $each){
				$cells = wf_TableCell($each['company_name']);
				$rows.= wf_TableRow($cells);
			}
		}
		
		$grid = wf_TableBody($rows);
		return $grid;
	}
	
	function web_CompanyAddForm(){
		$cells = wf_TableCell(__('Company name'));
		$cells.= wf_TableCell('','');
		$rows = wf_TableRow($cells,'row1');
		
		$cells = wf_HiddenInput('addcompany','true');
		$cells.= wf_TableCell(wf_TextInput('companyname','','',true,'20'));
		$cells.= wf_TableCell(wf_Submit(__('Add')));
		$rows.= ($cells);
		
		$result = wf_Form('','POST',wf_TableBody($rows,'30%'),'glamour');
				
		return $result;		
	}
	
	function web_ShowSettingsMenu(){
		
		$result= '';
	}
	
	function web_FooterMenu(){
		$result= wf_BackLink("?module=logistis2");
		
		return($result);
	}
	
	// Inserts a category into the database
	// Parameters: 
	//	$name = Category name
	//	$parentid = Parent category ID (0 = top level category)
	function addCategory($name,$parentid){
		$query = "INSERT INTO `logistis_cattypes` (`parent_id`,`category`) VALUES ('$parentid','$name')";
		nr_query($query);
		rcms_redirect("?module=logistis_settings");
	}
	function addCompany($company_name){
		$query = "INSERT INTO `logistis_companies` (`company_name`) VALUES ('$company_name')";
		nr_query($query);
		rcms_redirect("?module=logistis_settings");
	}
	
	//Add category if there is a request
	if(wf_CheckPost(array('addcategory'))){
		$name = mysql_real_escape_string($_POST['catname']);
		$parentid = mysql_real_escape_string($_POST['catid']);
		addCategory($name, $parentid);		
	}
	if(wf_CheckPost(array('addcompany'))){
		$company_name = mysql_real_escape_string($_POST['companyname']);
		addCompany($company_name);
	}
	show_window(__('Categories'),web_ShowCats(0));
	show_window(__('Add category'),web_ShowCatSettings());
	show_window(__('Companies'),web_ShowCompanySettings());
	show_window(__('Add company'),web_CompanyAddForm());
	show_window('',web_FooterMenu());
}
?>
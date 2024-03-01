<?

if(cfr('PAYFIND')){
	if(isset($_GET['id'])){
		$inv_id = stripslashes($_GET['id']);
		$query = "SELECT * FROM `logistis_invoices` INNER JOIN `payments` ON `payments`.`id`=`logistis_invoices`.`payment_id`  WHERE `logistis_invoices`.`id`='$inv_id'";
		$invoice = simple_query($query);
		
		$cells = wf_TableCell("Invoice number");
		$cells.= wf_TableCell("Customer name");
		$rows = wf_TableRow($cells);
	}
	
	
} else {
	die("Problem");
}
?>
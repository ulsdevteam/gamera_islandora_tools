#!/usr/local/bin/perl 

use CGI;
use Spreadsheet::WriteExcel;

$cgi = new CGI;
my $idno = $cgi->param('idno');
unless ($idno) {

	showForm();

}

unless (-f "/usr/local/dlxs/prep/a/ascead/xml/$idno.xml") {
	showForm("$idno.xml not found.  Please try again.");
}

print "Content-Disposition: attachment; filename=$idno.xls\n";
print "\n";

my $workbook = Spreadsheet::WriteExcel->new("-");
my $sheet1 = $workbook->add_worksheet();
my $format = $workbook->add_format(num_format => '@');

my $i = 0;
my @items = `java -jar /usr/local/dlxs/prep/bin/saxon/saxon8.jar -s /usr/local/dlxs/prep/a/ascead/xml/$idno.xml generateInventory.xsl`;

foreach my $row (@items)
{
	my @fields = split /\t/, $row;
	my $j = 0;	
	foreach $field (@fields) {
		$sheet1->write_string("$i", "$j", "$field");
		$j++;
	}
	$i++;
}

sub showForm {

	my $message = shift();
	print $cgi->header();
	print $cgi->start_html();
	print qq(<h1>Generate a spreadsheet for barcoding an ASC manuscript collection:</h1);
	print qq(<form method="POST" action=""><label>enter ais collection number, e.g. ais199701</label>
			 <input type="text" name="idno" size="20" /><input type="submit" /></form>);
	if ($message) {
		print qq(<p><font color="#C00">$message</font></p>);
	}
	print $cgi->end_html();



	exit 0;


}

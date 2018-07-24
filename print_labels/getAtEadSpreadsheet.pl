#!/usr/local/bin/perl 

use CGI;
use Spreadsheet::WriteExcel;
use File::Basename;

$cgi = new CGI;
my $idno = $cgi->param('idno');
$filename = $cgi->param('uploaded_file');
$tmpfilename = $cgi->tmpFileName($filename);

unless ($filename) {
	showForm();
}

unless (-f $tmpfilename) {
	showForm("$filename not found.  Please try again.");
}

my $base = basename($filename);
print "Content-Disposition: attachment; filename=${base}.xls\n";
print "\n";

my $workbook = Spreadsheet::WriteExcel->new("-");
my $sheet1 = $workbook->add_worksheet();
my $format = $workbook->add_format(num_format => '@');
`perl -pi -e 's,<ead [^>]*?>,<ead xmlns:ns2="http://www.w3.org/1999/xlink">,' $tmpfilename`; 
my $i = 0;
my @items = `java -jar /usr/local/dlxs/prep/bin/saxon/saxon8.jar -s $tmpfilename recurse.xsl`;

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
	print qq(<h1>Generate a spreadsheet for labeling an ASC manuscript collection:</h1>);
	print $cgi->start_multipart_form();
	print qq(<p>Browse for EAD XML file to process: </p>);
	print $cgi->filefield('uploaded_file','starting value',50,80);
	print qq(<p>);
	print $cgi->submit('upload', 'go');
	print qq(</p>);
	print $cgi->end_multipart_form();
	if ($message) {
		print qq(<p><font color="#C00">$message</font></p>);
	}
	print $cgi->end_html();

	exit 0;
}

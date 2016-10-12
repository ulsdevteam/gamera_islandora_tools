<xsl:stylesheet version="1.0" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
   <xsl:output method="xml" indent="yes" omit-xml-declaration="yes" />

   <xsl:template match="/ | @* | node()">
         <xsl:copy>
           <xsl:apply-templates select="@* | node()" />
         </xsl:copy>
   </xsl:template>
   <xsl:template match="/mods:mods/mods:relatedItem[@type='host']/mods:titleInfo/mods:title">
    <mods:title>Henry Clay Frick Business Records</mods:title>
   </xsl:template>
</xsl:stylesheet>

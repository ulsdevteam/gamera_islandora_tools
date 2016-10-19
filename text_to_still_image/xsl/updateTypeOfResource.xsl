<xsl:stylesheet version="1.0" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
   <xsl:output method="xml" indent="yes" omit-xml-declaration="yes" />

   <xsl:template match="/ | @* | node()">
         <xsl:copy>
           <xsl:apply-templates select="@* | node()" />
         </xsl:copy>
   </xsl:template>
   <xsl:template match="/mods:mods/mods:typeOfResource">
    <mods:typeOfResource>still image</mods:typeOfResource>
   </xsl:template>
</xsl:stylesheet>

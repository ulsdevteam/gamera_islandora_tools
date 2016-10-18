<xsl:stylesheet version="1.0" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
   <xsl:output method="xml" indent="yes" omit-xml-declaration="yes" />

   <xsl:template match="/ | @* | node()">
         <xsl:copy>
           <xsl:apply-templates select="@* | node()" />
         </xsl:copy>
   </xsl:template>

   <!-- Select the empty namePart depositor that has no value and do nothing (do not copy) when the node appears. -->
   <xsl:template match="/mods:mods/mods:name[mods:namePart[not(node())] and mods:role/mods:roleTerm[@type='text' and text()='depositor']]"/>

</xsl:stylesheet>

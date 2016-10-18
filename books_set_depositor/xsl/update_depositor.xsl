<xsl:stylesheet version="1.0" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
   <xsl:output method="xml" indent="yes" omit-xml-declaration="yes" />

   <xsl:template match="/ | @* | node()">
         <xsl:copy>
           <xsl:apply-templates select="@* | node()" />
         </xsl:copy>
   </xsl:template>


   <xsl:template match="/mods:mods/mods:name[count(mods:role/mods:roleTerm[@type='text' and text()='depositor']) = 0]">
      <mods:name>
        <mods:namePart>University of Pittsburgh</mods:namePart>
        <mods:role>
          <mods:roleTerm type="text">depositor</mods:roleTerm>
        </mods:role>
      </mods:name>
     <xsl:copy>
        <xsl:apply-templates select="@* | node()" />
      </xsl:copy>
   </xsl:template>


   <xsl:template match="/mods:mods[count(mods:name) = 0]">
     <xsl:copy>
        <xsl:apply-templates select="@*" />
        <mods:name>
          <mods:namePart>University of Pittsburgh</mods:namePart>
          <mods:role>
            <mods:roleTerm type="text">depositor</mods:roleTerm>
          </mods:role>
        </mods:name>
        <xsl:apply-templates select="node()" />
      </xsl:copy>
   </xsl:template>

   <xsl:template match="/mods:mods/mods:name/mods:namePart[not(node())]"><mods:namePart>University of Pittsburgh</mods:namePart></xsl:template>

</xsl:stylesheet>

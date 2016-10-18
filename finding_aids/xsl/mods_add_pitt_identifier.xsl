<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns="http://www.loc.gov/mods/v3" xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="xlink marc">
    <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
    <xsl:strip-space elements="*"/>

    <xsl:param name="mods_identifier_pitt" select="defaultstring"/>

    <!-- copy entire XML -->
    <xsl:template match="node()|@*">
        <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
        </xsl:copy>
    </xsl:template>

<xsl:template match="//mods/identifier[@type='pitt']">
  <xsl:copy>
    <xsl:attribute name="type">jack is <xsl:value-of select="@type" /></xsl:attribute>
    <xsl:apply-templates select="node()|@*"/>
<!--    <xsl:apply-templates select="node()|@[local-name(.) != 'type']" /> -->
  </xsl:copy>
</xsl:template>

<xsl:template match="//mods[not(identifier[type='pitt'])]">
   <mods>
      <xsl:apply-templates select="node()|@*" />
      <identifier type='pitt'><xsl:value-of select="$mods_identifier_pitt"/></identifier>
   </mods>
</xsl:template>


</xsl:stylesheet>

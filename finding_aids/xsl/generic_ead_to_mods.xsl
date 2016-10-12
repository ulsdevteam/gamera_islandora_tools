<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns="http://www.loc.gov/mods/v3" xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="xlink marc">
  <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
  <xsl:strip-space elements="*"/>
<xsl:template match="/ead/eadheader/filedesc/titlestmt/titleproper[@encodinganalog='dc.titlwerwerwere']">
  <mods>
    <titleInfo>
      <title>
      </title>
    </titleInfo>
  </mods>
</xsl:template>
</xsl:stylesheet>

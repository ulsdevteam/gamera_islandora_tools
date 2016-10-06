<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns="http://www.loc.gov/mods/v3" xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="xlink marc">
    <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
    <xsl:strip-space elements="*"/>

    <xsl:param name="mods_identifier_pitt" select="defaultstring"/>

    <!-- copy entire XML -->
    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- add "identifier" node or update existing for the mods_identifier_pitt_s -->
    <xsl:template match="/mods">
        <xsl:choose>
            <xsl:when test="count(identifier[@type='pitt'])">
                <xsl:copy select="identifier[@type='pitt']">
                    Testing
                </xsl:copy>
            </xsl:when>
            <xsl:otherwise>
                <identifier type="pitt">Testing</identifier>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
</xsl:stylesheet>

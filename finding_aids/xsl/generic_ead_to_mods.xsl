<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ead="urn:isbn:1-931666-22-9"
    xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink"
    exclude-result-prefixes="ead mods xlink xsl" version="1.0">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:strip-space elements="*"/>

    <xsl:template match="/">
        <xsl:apply-templates select="ead:ead/ead:archdesc"/>
    </xsl:template>

    <xsl:template match="ead:ead/ead:archdesc">
        <mods:mods
            xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-3.xsd"
            xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
            <mods:titleInfo>
                <mods:title>
                    <xsl:value-of select="normalize-space(ead:did/ead:unittitle/text())"/>
                </mods:title>
            </mods:titleInfo>
            <xsl:for-each select="ead:did/ead:origination">
                <mods:name>
                    <mods:namePart>
                        <xsl:value-of select="."/>
                    </mods:namePart>
                    <xsl:if test="@label">
                        <mods:role>
                            <mods:roleTerm>
                                <xsl:value-of select="@label"/>
                            </mods:roleTerm>
                        </mods:role>
                    </xsl:if>
                </mods:name>
            </xsl:for-each>
            <mods:name>
                <mods:namePart>Senator John Heinz History Center</mods:namePart>
                <mods:role>
                    <mods:roleTerm type="text">depositor</mods:roleTerm>
                </mods:role>
            </mods:name>
            <mods:originInfo>
                <mods:dateCreated>
                    <xsl:value-of select="ead:did/ead:unitdate|ead:did/ead:unittitle/ead:unitdate"/>
                </mods:dateCreated>
            </mods:originInfo>
            <mods:abstract>
                <xsl:value-of select="normalize-space(ead:did/ead:abstract)"/>
            </mods:abstract>
            <mods:identifier>
                <xsl:value-of select="normalize-space(../ead:eadheader/ead:eadid)"/>
            </mods:identifier>
            <mods:identifier>
                <xsl:value-of select="ead:did/ead:unitid"/>
            </mods:identifier>
            <mods:typeOfResource>mixed material</mods:typeOfResource>
            <mods:genre>archival collection</mods:genre>

        </mods:mods>
    </xsl:template>
</xsl:stylesheet>

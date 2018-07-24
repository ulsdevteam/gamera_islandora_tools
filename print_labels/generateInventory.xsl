<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
    <xsl:output method="text" indent="no" encoding="utf-8"/>	
    <xsl:template match="/">
        <xsl:for-each select="//did">
            <xsl:for-each select="container[@type='file'] | container[@type='folder'] | container[@type='Folder'] | container[@type='Folders'] | container[@type='Item'] | container[@type='oversize'] | container[@type='Oversize'] | container[@type='volume'] | container[@type='Volume'] | container[@type='Volumes']">
                <xsl:variable name="parentContainer" select="@parent" />
                <xsl:text>&#09;</xsl:text>
                 <xsl:value-of select="normalize-space(//eadid)" />
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="//container[@id=$parentContainer]/@type" />
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="//container[@id=$parentContainer]" />
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="@type" />
                <xsl:text>&#09;</xsl:text>
                 <xsl:value-of select="." />
                <xsl:text>&#09;</xsl:text>               
                <xsl:value-of select="normalize-space(../unittitle)" />
                <xsl:text>&#09;</xsl:text>               
                <xsl:value-of select="..//unitdate" />
                <xsl:text>&#09;</xsl:text>               
                <xsl:call-template name="xpathTrace" />
                <xsl:text>&#10;</xsl:text>
            </xsl:for-each>   
        </xsl:for-each>
    </xsl:template>
    <xsl:template name="xpathTrace">
            <xsl:variable name="theResult">
                    <xsl:variable name="theNode" select="."/>
                    <xsl:for-each select="$theNode |  $theNode/ancestor-or-self::node()[..]">
                        <xsl:element name="slash">/</xsl:element>
                                <xsl:element name="nodeName">
                                    <xsl:value-of select="name()"/>
                                    <xsl:variable name="thisPosition"  select="count(preceding-sibling::*[name(current()) =  name()])"/>
                                    <xsl:variable name="numFollowing" select="count(following-sibling::*[name(current()) =  name()])"/>
                                    <xsl:if test="$thisPosition + $numFollowing > 0">
                                        <xsl:value-of select="concat('[', $thisPosition + 1, ']')"/>
                                    </xsl:if>
                                </xsl:element>
                    </xsl:for-each>
                </xsl:variable>
            <xsl:value-of select="$theResult"/>
    </xsl:template>
</xsl:stylesheet>
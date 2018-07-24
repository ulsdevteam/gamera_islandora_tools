<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
    <xsl:output method="text" indent="no" encoding="utf-8"/>	
<xsl:variable name="decrement">1</xsl:variable>
<xsl:template match="/">
<xsl:apply-templates select="//container | //c" />
</xsl:template>
    
    <xsl:template name="container" match="//container | //c">
        <xsl:param name="containers" select="//container" />
        <xsl:if test=".[@type='file'] | .[@type='folder'] | .[@type='Folder'] | .[@type='Folders'] | .[@type='Item'] | .[@level='item'] | .[@type='oversize'] | .[@type='Oversize'] | .[@type='volume'] | .[@type='Volume'] | .[@type='Volumes']">
                <xsl:value-of select="normalize-space(//eadid)" />
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="normalize-space(//titleproper)" />
                <xsl:text>&#09;</xsl:text>
                <xsl:text>Box</xsl:text>
                <xsl:text>&#09;</xsl:text>
                <xsl:call-template name="box_recurse">
                    <xsl:with-param name="the_c" select="$containers" />
                    <xsl:with-param name="index" select="position()" />
                </xsl:call-template>
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="@type" />
                <xsl:text>&#09;</xsl:text>
                <xsl:value-of select="."/>
                <xsl:text>&#09;</xsl:text>               
                <xsl:value-of select="normalize-space(../unittitle)" />
                <xsl:text>&#09;</xsl:text>               
                <xsl:value-of select="..//unitdate" />            
                <xsl:text>&#10;</xsl:text>
            </xsl:if>
    </xsl:template>
    
    <xsl:template name="box_recurse">
        <xsl:param name="the_c" />
        <xsl:param name="index" />
        <xsl:choose>
            <xsl:when test="$the_c[$index][@type='Box']">
                <xsl:value-of select="$the_c[$index]"/>
            </xsl:when>
            <xsl:when test="$index - $decrement != 0">
                <xsl:call-template name="box_recurse">
                    <xsl:with-param name="the_c" select="$the_c" />
                    <xsl:with-param name="index" select="$index - $decrement" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:text>0</xsl:text>
            </xsl:otherwise>
        </xsl:choose>
 
    </xsl:template>
</xsl:stylesheet>

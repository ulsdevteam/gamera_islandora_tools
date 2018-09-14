<?xml version="1.0" encoding="UTF-8"?>
<!--
  * remove any local-file referenced stylesheet like < ? xml-stylesheet type="text/xsl" href="transform.xml" ? >
  *
  * We are going to attempt to resolve all of the inconsistencies you found. Here is the list, as a reminder:
  *  •  Container type (box, folder, etc.) does not appear
  *  •  Box numbers do not appear
  *  •  The first folder of each box does not appear
  *  •  In the Allegheny Conference finding aid, the container list for the first series does not appear.
  *     Not sure if this is an anomaly or a more widespread issue.
  *  •  Controlled Access Terms do not appear
  *  •  Remove repository address from top of finding aid
  *  •  Properly display “Title” and “Extent” headers
  *
  * I also found a couple other inconsistencies that I asked Brian to attempt to resolve:
  *  •  Display proper scope/content header in container list
  *  •  Remove emphasis/bolding in arrangement
  *
 -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ead="urn:isbn:1-931666-22-9">
  <xsl:output method="xml" indent="yes"/>
  <xsl:template match="node() | @*">
    <xsl:copy>
      <xsl:apply-templates select="node() | @*"/>
    </xsl:copy>
  </xsl:template>

  <!-- remove any local-file referenced xml-stylesheet -->
  <xsl:template match="processing-instruction('xml-stylesheet')"/>

  <!-- 2.  Pull all inner nodes within the ControlAccess node up to the first node level and remove
           any <head>…</head> tag values -->
  <xsl:template match="ead:controlaccess">
    <xsl:apply-templates select="node()[local-name() != 'head']"/>
  </xsl:template>
  <xsl:template match="ead:ead/ead:archdesc/ead:controlaccess">
    <xsl:copy>
      <xsl:apply-templates select="node()[local-name() != 'head']"/>
    </xsl:copy>
  </xsl:template>

  <!--  3.  For any unit title, move any unit date -->
  <!-- 15. remove ", " when first two characters of any unittitle -->
  <xsl:template match="ead:unittitle">
    <xsl:copy>
      <xsl:choose>
        <xsl:when test="substring(normalize-space(text()), string-length(normalize-space(text())), 1) = ','">
          <xsl:choose>
            <xsl:when test="substring(normalize-space(text()), 1, 2) = ', '">
              <xsl:value-of select="substring(normalize-space(text()), 3, string-length(normalize-space(text())) - 2)"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="substring(normalize-space(text()), 1, string-length(normalize-space(text())) - 1)"/>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="text()"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:copy>
    <xsl:for-each select="ead:unitdate">
      <xsl:copy-of select="."/>
    </xsl:for-each>
  </xsl:template>

  <!-- 4. Add label="Mixed Materials" to any Folder, Box, Volume, or Shelf @type container nodes. -->
<!--  <xsl:template match="ead:container[normalize-space(@type)='Folder' and not (@label)] | ead:container[normalize-space(@type)='Box' and not(@label)] |
     ead:container[normalize-space(@type)='folder' and not(@label)] | ead:container[normalize-space(@type)='box' and not(@label)] | 
     ead:container[normalize-space(@type)='Volume' and not (@label)] | ead:container[normalize-space(@type)='Shelf' and not(@label)] |
     ead:container[normalize-space(@type)='volume' and not(@label)] | ead:container[normalize-space(@type)='shelf' and not(@label)]">
-->    <!-- copy me and my attributes and my subnodes, applying templates as necessary, and
         add a label attribute set to "Mixed Materials" -->
<!--    <xsl:copy>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="label">Mixed Materials</xsl:attribute>
      <xsl:apply-templates select="node()"/>
    </xsl:copy>
  </xsl:template> -->

  <!-- 14. duplicate row issue: remove the label="Mixed Materials" from the
        2nd containers of <c## level="file"><did><container *>
       and add an id to the solitary containers under did -->
  <!-- TODO: can omit this step when the TODO for #4 is done -->
  <xsl:template match="ead:container">
    <xsl:copy>
    <xsl:if test="((count(parent::ead:did) = 1 and count(preceding-sibling::ead:container) = 0)
      and ((normalize-space(@type)='Folder' and not (@label)) or (normalize-space(@type)='Box' and not(@label)) or
     (normalize-space(@type)='folder' and not(@label)) or (normalize-space(@type)='box' and not(@label)) or
     (normalize-space(@type)='Volume' and not (@label)) or (normalize-space(@type)='Shelf' and not(@label)) or
     (normalize-space(@type)='volume' and not(@label)) or (normalize-space(@type)='shelf' and not(@label))))">
        <xsl:apply-templates select="@*"/>
        <xsl:attribute name="label">Mixed Materials</xsl:attribute>
        <!-- do not want becuase children not added yet REMOVED: <xsl:apply-templates select="node()"/> -->
    </xsl:if>

    <xsl:choose>
      <xsl:when test="count(parent::ead:did) = 1 and count(preceding-sibling::ead:container) = 0 and count(following-sibling::ead:container) = 0">
        <!-- one containers under the did AND I am the first container -->
          <xsl:attribute name="id">
            <xsl:value-of select="'number-'"/>
            <xsl:number level="any"/>
          </xsl:attribute>
          <xsl:apply-templates select="node() | @*"/>
      </xsl:when>

      <xsl:when test="count(parent::ead:did) = 1 and count(preceding-sibling::ead:container) = 1">
        <!-- two containers under the did AND I am the second container -->
          <xsl:apply-templates select="node() | @*[name()!='label']"/>
      </xsl:when>

      <!-- any container that is not in a did -->
      <xsl:otherwise>
          <xsl:apply-templates select="node() | @*" />
      </xsl:otherwise>
    </xsl:choose>

    </xsl:copy>
  </xsl:template>

  <!-- 5. Remove repository address from top of finding aid... replace it with "Detre Library & Archives, Heinz History Center" -->

  <!-- 6. display the Extent archdesc[@level="collection"]/did/physdesc/extent by removing the @label from the physdesc node -->
  <!-- empty template suppresses this attribute -->
  <xsl:template match="ead:physdesc/@label"/>

  <!-- 7. Remove the label from the prefcite -->
  <xsl:template match="ead:note[@label='prevcite']">
    <xsl:apply-templates/>
  </xsl:template>

  <!-- 8. Set the Repository value to "Detre Library & Archives, Heinz History Center" remove the entire address value -->
  <xsl:template match="//ead:repository">
    <xsl:element name="repository">
       <xsl:element name="corpname">
         <xsl:text>Detre Library &amp; Archives, Heinz History Center</xsl:text>
       </xsl:element>
     </xsl:element>
  </xsl:template>

  <!-- 9. remove the bolded / emphasis items in the arrangement nodes -->
  <!-- ????? no idea what exactly matches for this issue -->

  <!-- 10. Add a <head>Scope and Content Notes</head> to scopecontent nodes if they do not have it already. -->
  <xsl:template match="//ead:scopecontent[not(ead:head)]">
    <!-- copy me and my attributes and my subnodes, applying templates as necessary, and
         add the <head>...</head> value -->
    <xsl:copy>
      <xsl:element name="ead:head">
        <xsl:text>Scope and Content Notes</xsl:text>
      </xsl:element>
      <xsl:apply-templates select="node()"/>
    </xsl:copy>
  </xsl:template>

  <!-- 11.  Make unitid first. -->
  <xsl:template match="ead:did">
    <xsl:copy>
      <xsl:apply-templates select="ead:unitid"/>
      <xsl:apply-templates select="node()[local-name() != 'unitid']"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>

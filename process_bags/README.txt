This process will allow ingest or update objects using folder that were created via the Bag-it process.

NOTE:  the resultant model for all objects is hard-coded as islandora:sp_large_image_cmodel.  Although the Bag-it
process creates a RELS-EXT file, it would have to be parsed in order to determine the model type.  For the case
of my needs, I knew that all objects would be islandora:sp_large_image_cmodel so I did not even bother to parse
the RELS-EXT at this time.

This was written in order to export specific objects from production to my local development environment, but the code
may be useful for another process that works with Bag-it folders.  Also, this script has the code needed to ingest an 
object along with the related datastreams.

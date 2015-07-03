<?php

/*
**************************************************************************************************************************
** CORAL Resources Module v. 1.2
**
** Copyright (c) 2010 University of Notre Dame
**
** This file is part of CORAL.
**
** CORAL is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
**
** CORAL is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License along with CORAL.  If not, see <http://www.gnu.org/licenses/>.
**
**************************************************************************************************************************
*/

include_once 'directory.php';

$util = new Utility();
$config = new Configuration();

$filename = $argv[1];
$delimiter = "\t";
//$resourceObj = new Resource();
$handle = fopen($filename, "r");
$row = 0;
while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
    if ($row == 0) {
        foreach ($data as $key => $value) {
            $cols[strtolower(str_replace(' ', '_', $value))] = $key;
        }
    } else {

            $l = new License();

            // Let's search the parent resource
            $parentResourceName = $data[$cols['database_code']];
            /*
            $resources = $resourceObj->getResourceByTitle($parentResourceName);
            $resource = $resources[0];
            // It would be nice to be able to call other modules object, but nope, so...
            */
            $rdbName = $config->settings->resourcesDatabaseName;
            $query = "SELECT resourceID  FROM $rdbName.Resource WHERE UPPER(titleText) = '" . str_replace("'", "''", strtoupper($parentResourceName)) . "'";
            $result = $l->db->processQuery($query, 'assoc');

            // Getting the first son (by default)
            $query = "SELECT resourceID FROM $rdbName.ResourceRelationship WHERE relatedResourceID = " . $result['resourceID'] . " LIMIT 1";
            $result = $l->db->processQuery($query, 'assoc');

            // Getting first son organization
            /*
            $organizations = $resource->getOrganizationArray();
            $organization = $organizations[0];
            // It would be nice to be able to call other modules object, but nope, so...
            */
            $query = "SELECT organizationID  FROM $rdbName.ResourceOrganizationLink WHERE ResourceID = " . $result['resourceID'];
            $result = $l->db->processQuery($query, 'assoc');

            // Now we can create the license attached to the provider
            $l = new License();
            $l->shortName = $data[$cols['license_name']];
            $l->statusID = 2;
            $l->organizationID = $result['organizationID'];
            $l->consortiumID = $result['organizationID'];
            $l->statusDate = date( 'Y-m-d' );
            $l->createDate = $data[$cols['date_debut']];
            $ret = $l->save();
            echo "License " . $data[$cols['license_name']] . " saved \n";

            // Get LicenseID;
            $ldbName = $config->database->name;
            $query = "SELECT licenseID from $ldbName.License WHERE UPPER(shortName) = '" . str_replace("'", "''", strtoupper($data[$cols['license_name']])) . "' ORDER BY LicenseID DESC LIMIT 1";
            $result = $l->db->processQuery($query, 'assoc');


            // Check for document type
            $query = "SELECT documentTypeID From DocumentType WHERE UPPER(shortName) = '" . str_replace("'", "''", strtoupper($data[$cols['type']])) . "'";
            $result2 = $l->db->processQuery($query, 'assoc');
            if ($result2['documentTypeID']) {
                $documentTypeID = $result2['documentTypeID'];
            }

            // Create document
            $d = new Document();
            $d->shortName = $data[$cols['license_name']] ;
            $d->documentTypeID = $documentTypeID ? $documentTypeID : 1;
            $d->licenseID = $result['licenseID'];
            $d->save();

            $query = "SELECT documentID from $ldbName.Document WHERE UPPER(shortName) = '" . str_replace("'", "''", strtoupper($data[$cols['license_name']])) . "' ORDER BY documentID DESC LIMIT 1";
            $result = $l->db->processQuery($query, 'assoc');

            // Auth users
            $e = new Expression();
            $e->documentID = $result['documentID'];
            $e->documentText = $data[$cols['authorized_users']];
            $e->expressionTypeID = 1;
            $e->productionUseInd = 1;
            $e->save();

            // Digitally copy
            $e = new Expression();
            $e->documentID = $result['documentID'];
            $e->documentText = $data[$cols['digitally_copy']];
            $e->expressionTypeID = 11;
            $e->productionUseInd = 1;
            $e->save();


            $query = "SELECT expressionID from $ldbName.Expression WHERE expressionTypeID = 11 ORDER BY expressionID DESC LIMIT 1";
            $result = $l->db->processQuery($query, 'assoc');

            $n = new ExpressionNote();
            $n->expressionID = $result['expressionID'];
            $n->note = $data[$cols['digitally_copy_note']];
            $n->save();


    }
    $row++;
}
?>

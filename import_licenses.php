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
            $cols[strtolower(str_replace(' ', '_', trim(str_replace(array(',', '?', '-' , '(', ')'), '', utf8_decode($value)))))] = $key;
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

            // Get LicenseID;
            $ldbName = $config->database->name;
            $query = "SELECT licenseID from $ldbName.License WHERE UPPER(shortName) = '" . str_replace("'", "''", strtoupper($data[$cols['license_name']])) . "' ORDER BY LicenseID DESC LIMIT 1";
            $result = $l->db->processQuery($query, 'assoc');
            echo "License " . $data[$cols['license_name']] . " (" . $result['licenseID'] . ") saved \n";


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

            // Empty check
            echo "\nEmpty fields for row $row: ";
            foreach ($cols as $key => $value) {
                if (!$data[$cols[$key]]) {
                    echo " $key - ";
                }
            }
            echo "\n";

            addExpressionNote($result['documentID'], $data[$cols['authorized_users']], $data[$cols['authorized_users_note']], 'Authorized Users');
            addExpressionNote($result['documentID'], $data[$cols['digitally_copy']], $data[$cols['digitally_copy_note']], 'Digitally Copy');
            addExpressionNote($result['documentID'], $data[$cols['concurrent_users']], $data[$cols['concurrent_users_note']], 'Concurrent Users');
            addExpression($result['documentID'], $data[$cols['fair_use_clause_indicator']], 'Fair Use');
            addExpression($result['documentID'], $data[$cols['database_protection_override_clause_indicator']], 'Database Protection Override');
            addExpression($result['documentID'], $data[$cols['all_rights_reserved_indicator']], 'All Rights Reserved');
            addExpression($result['documentID'], $data[$cols['citation_requirement_detail']], 'Citation Requirement');
            addExpressionNote($result['documentID'], $data[$cols['print_copy']], $data[$cols['print_copy_note']], 'Print Copy');
            addExpressionNote($result['documentID'], $data[$cols['scholarly_sharing']], $data[$cols['scholarly_sharing_note']], 'Scholarly Sharing');
            addExpression($result['documentID'], $data[$cols['distance_learning']], 'Distance Learning');
            addExpression($result['documentID'], "ILL General: " . $data[$cols['ill_general']], 'Interlibrary Loan');
            addExpression($result['documentID'], "ILL Secure Electronic: " . $data[$cols['ill_secure_electronic']], 'Interlibrary Loan');
            addExpression($result['documentID'], "ILL Secure Electronic (email): " . $data[$cols['ill_electronic_email']], 'Interlibrary Loan');
            addExpressionNote($result['documentID'], "ILL Record Keeping: " . $data[$cols['ill_record_keeping']], $data[$cols['ill_record_keeping_note']], 'Interlibrary Loan');
            addExpressionNote($result['documentID'], $data[$cols['course_reserve']], $data[$cols['course_reserve_note']], 'Course Reserve');
            addExpressionNote($result['documentID'], $data[$cols['course_pack_print']] . "\n" . $data[$cols['course_pack_electronic']], $data[$cols['course_pack_note']], 'Course Pack');
            addExpressionNote($result['documentID'], $data[$cols['remote_access']], $data[$cols['remote_access_note']], 'Remote Access');
            addExpressionNote($result['documentID'], 'Other Use Restrictions (Staff Note)', $data[$cols['other_use_restrictions_staff_note']], 'Other use Restrictions (Staff)');
            addExpressionNote($result['documentID'], 'Other Use Restrictions (Public Note)', $data[$cols['other_use_restrictions_public_note']], 'Other use Restrictions (Public)', 'Display');
            addExpressionNote($result['documentID'], $data[$cols['perpetual_access_right']] . "\n" . $data[$cols['perpetual_access_holdings']], $data[$cols['perpetual_access_note']], 'Perpetual Access');
            addExpressionNote($result['documentID'], $data[$cols['licensee_termination_right']] . "\n" . $data[$cols['licensee_termination_holdings']] . "\n" . $data[$cols['licensee_notice_period_for_termination_number']] . ' ' . $data[$cols['licensee_notice_period_for_termination_unit']], $data[$cols['licensee_termination_condition_note']], 'Licensee Termination');
            addExpressionNote($result['documentID'], $data[$cols['licensor_termination_right']] . "\n" . $data[$cols['licensor_termination_condition']] . "\n" . $data[$cols['licensor_notice_period_for_termination_number']] . ' ' . $data[$cols['licensor_notice_period_for_termination_unit']], $data[$cols['licensor_termination_condition_note']], 'Licensor Termination');
            
            addExpressionNote($result['documentID'], "Termination Right", $data[$cols['termination_right_note']], "Termination Right");
            addExpressionNote($result['documentID'], $data[$cols['termination_requirements']], $data[$cols['termination_requirements_note']], "Termination Requirements");
            addExpressionNote($result['documentID'], "Terms", $data[$cols['terms_note']], "Terms");
            addExpressionNote($result['documentID'], "Local Use Terms", $data[$cols['local_use_terms_note']], "Local Use Terms");
            addExpression($result['documentID'], $data[$cols['governing_law']], "Governing Law");
            addExpression($result['documentID'], $data[$cols['applicable_copyright_law']], "Applicable Copyright Law");
            addExpression($result['documentID'], $data[$cols['cure_period_for_breach_number']] . " " . $data[$cols['cure_period_for_breach_unit']], "Cure Period For Breach");
            addExpression($result['documentID'], $data[$cols['renewal_type']], "Renewal Type");
            addExpression($result['documentID'], $data[$cols['non-renewal_notice_period_number']] . " " . $data[$cols['non-renewal_notice_period_unit']], "Non-Renewal Notice Period");
            addExpressionNote($result['documentID'], $data[$cols['archiving_right']] . "\n" . $data[$cols['archiving_format']], $data[$cols['archiving_note']], 'Archiving');
            addExpressionNote($result['documentID'], $data[$cols['preprint_archive_allowed']] . "\n" . $data[$cols['preprint_archive_conditions']] . "\n" . $data[$cols['preprint_archive_restrictions_number']] . " " . $data[$cols['preprint_archive_restrictions_unit']], $data[$cols['preprint_archive_note']], 'Pre-print Archive');
            addExpressionNote($result['documentID'], $data[$cols['postprint_archive_allowed']] . "\n" . $data[$cols['postprint_archive_conditions']] . "\n" . $data[$cols['postprint_archive_restrictions_number']] . " " . $data[$cols['postprint_archive_restrictions_unit']], $data[$cols['post-print_archive_note']], 'Post-print Archive');
            addExpressionNote($result['documentID'], $data[$cols['incorporation_of_images_figures_and_tables_right']], $data[$cols['incorporation_of_images_figures_and_tables_note']], 'Incorporation of Images, Figures, and Tables');
            addExpressionNote($result['documentID'], $data[$cols['public_performance_right']], $data[$cols['public_performance_note']], 'Public Performance');
            addExpressionNote($result['documentID'], $data[$cols['training_materials_right']], $data[$cols['training_materials_note']], 'Training Materials');


    }
    $row++;
}

function addExpression($documentID, $documentText, $et_shortName, $noteType = null) {
    if (!$documentText) { 
        echo "Warning ! No text for expression $et_shortname";
        return;
    }
    $e = new Expression();
    $expressionTypeID = createExpressionType($et_shortName, $noteType);
    $e->documentID = $documentID;
    $e->documentText = trim($documentText);
    $e->expressionTypeID = $expressionTypeID;
    $e->productionUseInd = 1;
    $e->save();
    echo "Expression " . trim($documentText) . " created\n";
    return $e;
}

function addExpressionNote($documentID, $documentText, $note, $et_shortName, $noteType = null) {
    $expressionTypeID = createExpressionType($et_shortName, $noteType);
    $e = addExpression($documentID, $documentText, $et_shortName, $noteType);
    if (!$note) return;
    $n = new ExpressionNote();
    $n->expressionID = $e->expressionID;
    $n->note = $note;
    $n->save();
    echo "ExpressionNote " . substr($note, 0, 20) . " created\n";
    return $n;
}

function createExpressionType($shortName, $noteType = null) {
   if (expressionTypeExists($shortName)) return getExpressionTypeID($shortName);
   if ($noteType == null) $noteType = 'Internal';
   $et = new ExpressionType();
   $et->shortName = $shortName;
   $et->noteType = $noteType;
   $et->save();
   echo "ExpressionType $shortName created\n";
   return $et->expressionTypeID;
}

function getExpressionTypeID($shortName) {
    $e = new ExpressionType();
    $query = "SELECT expressionTypeID FROM ExpressionType WHERE UPPER(shortName) = '" . strtoupper($shortName) . "'";
    $count = $e->db->processQuery($query);
    return $count[0];
}

function expressionTypeExists($shortName) {
    $e = new ExpressionType();
    $query = "SELECT count(*) FROM ExpressionType WHERE UPPER(shortName) = '" . strtoupper($shortName) . "'";
    $count = $e->db->processQuery($query);
    return $count[0];
}
?>

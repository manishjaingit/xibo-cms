<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Media extends Data
{
    private $moduleInfoLoaded;
    private $regionSpecific;
    private $validExtensions;

    /**
     * Adds a new media record
     * @param <type> $fileId
     * @param <type> $type
     * @param <type> $name
     * @param <type> $duration
     * @param <type> $fileName
     * @param <type> $permissionId
     * @param <type> $userId
     * @return <type>
     */
    public function Add($fileId, $type, $name, $duration, $fileName, $permissionId, $userId)
    {
        $db =& $this->db;

        $extension = strtolower(substr(strrchr($fileName, '.'), 1));

        // Check that is a valid media type
        if (!$this->IsValidType($type))
            return false;

        // Check the extension is valid for that media type
        if (!$this->IsValidFile($extension))
            return $this->SetError(18, __('Invalid file extension'));

        // Validation
        if (strlen($name) > 100)
            return $this->SetError(10, __('The name cannot be longer than 100 characters'));

        if ($duration == 0)
            return $this->SetError(11, __('You must enter a duration.'));

        if ($db->GetSingleRow(sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d", $db->escape_string($name), $userId)))
            return $this->SetError(12, __('Media you own already has this name. Please choose another.'));

        // All OK to insert this record
        $SQL  = "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired ) ";
        $SQL .= "VALUES ('%s', '%s', '%s', '%s', %d, %d, 0) ";

        $SQL = sprintf($SQL, $db->escape_string($name), $db->escape_string($type),
            $db->escape_string($duration), $db->escape_string($fileName), $permissionId, $userId);

        if (!$mediaId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(13, __('Error inserting media.'));
            return false;
        }

        // Now move the file
        $libraryFolder 	= Config::GetSetting($db, 'LIBRARY_LOCATION');

        if (!rename($libraryFolder . 'temp/' . $fileId, $libraryFolder . $mediaId . '.' . $extension))
        {
            // If we couldnt move it - we need to delete the media record we just added
            $SQL = sprintf("DELETE FROM media WHERE mediaID = %d ", $mediaId);

            if (!$db->query($SQL))
                return $this->SetError(14, 'Error cleaning up after failure.');

            return $this->SetError(15, 'Error storing file.');
        }

        // Calculate the MD5 and the file size
        $storedAs   = $libraryFolder . $mediaId . '.' . $extension;
        $md5        = md5_file($storedAs);
        $fileSize   = filesize($storedAs);

        // Update the media record to include this information
        $SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $storedAs, $md5, $fileSize, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(16, 'Updating stored file location and MD5');
        }

        return $mediaId;
    }

    public function Edit()
    {
       $db =& $this->db;
    }

    public function Retire()
    {
        $db =& $this->db;
    }

    public function Delete()
    {
        $db =& $this->db;
    }

    private function IsValidType($type)
    {
        $db =& $this->db;

        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        return true;
    }

    private function IsValidFile($extension)
    {
        $db =& $this->db;

        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo())
                return false;
        }

        // TODO: Is this search case sensitive?
        return in_array($extension, $this->validExtensions);
    }

    /**
     * Loads some information about this type of module
     * @return <bool>
     */
    private function LoadModuleInfo($type)
    {
        $db =& $this->db;

        if ($type == '')
            return $this->SetError(18, __('No module type given'));

        $SQL = sprintf("SELECT * FROM module WHERE Module = '%s'", $db->escape_string($type));

        if (!$result = $db->query($SQL))
            return $this->SetError(19, __('Database error checking module'));

        if ($db->num_rows($result) != 1)
            return $this->SetError(20, __('No Module of this type found'));

        $row = $db->get_assoc_row($result);

        $this->moduleInfoLoaded = true;
        $this->regionSpecific   = Kit::ValidateParam($row['RegionSpecific'], _INT);
        $this->validExtensions 	= explode(',', Kit::ValidateParam($row['ValidExtensions'], _STRING));
        
        return true;
    }
}
?>

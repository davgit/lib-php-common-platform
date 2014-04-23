<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DreamFactory\Platform\Services\SwaggerManager;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );
$_commonResponses = SwaggerManager::getCommonResponses();

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getResources() - List all resources.',
                'nickname'         => 'getResources',
                'type'             => 'Resources',
                'event_name'       => '{api_name}.list',
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
                'notes'            => 'List the names of the available containers in this storage. ',
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'getContainers() - List all containers, optionally with properties.',
                'nickname'         => 'getContainers',
                'type'             => 'ContainersResponse',
                'event_name'       => '{api_name}.describe',
                'parameters'       => array(
                    array(
                        'name'          => 'include_properties',
                        'description'   => 'Return any properties of the container in the response.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'defaultValue'  => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
                'notes'            => 'List the names and any properties of the available containers in this storage.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createContainers() - Create one or more containers.',
                'nickname'         => 'createContainers',
                'type'             => 'ContainersResponse',
                'event_name'       => '{api_name}.create',
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of containers to create.',
                        'allowMultiple' => false,
                        'type'          => 'ContainersRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'check_exist',
                        'description'   => 'If true, the request fails when the container to create already exists.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'X-HTTP-METHOD',
                        'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                        'enum'          => array('GET', 'PUT', 'PATCH', 'DELETE'),
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'header',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
                'notes'            =>
                    'Post data should be a single container definition or an array of container definitions. ' .
                    'Alternatively, override the HTTP Method to pass containers to other actions.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteContainers() - Delete one or more containers.',
                'nickname'         => 'deleteContainers',
                'type'             => 'ContainersResponse',
                'event_name'       => '{api_name}.delete',
                'parameters'       => array(
                    array(
                        'name'          => 'names',
                        'description'   => 'List of containers to delete.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'force',
                        'description'   => 'Set force to true to delete all containers, otherwise \'names\' parameter is required.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'default'       => false,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
                'notes'            =>
                    'Pass a comma-delimited list of container names to delete. ' .
                    'Set \'force\' to true to delete all containers. ' .
                    'Alternatively, to delete by container records or a large list of names, ' .
                    'use the POST request with X-HTTP-METHOD = DELETE header and post containers.',
            ),
        ),
        'description' => 'Operations available for File Storage Service.',
    ),
    array(
        'path'        => '/{api_name}/{container}/',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getContainer() - List the container\'s content, including properties.',
                'nickname'         => 'getContainer',
                'type'             => 'ContainerResponse',
                'event_name'       => '{api_name}.{container}.describe',
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container from which you want to retrieve contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'include_properties',
                        'description'   => 'Include any properties of the container in the response.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'include_folders',
                        'description'   => 'Include folders in the returned listing.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => true,
                    ),
                    array(
                        'name'          => 'include_files',
                        'description'   => 'Include files in the returned listing.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => true,
                    ),
                    array(
                        'name'          => 'full_tree',
                        'description'   => 'List the contents of all sub-folders as well.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'zip',
                        'description'   => 'Return the content of the folder as a zip file.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            =>
                    'Use \'include_properties\' to get properties of the container. ' .
                    'Use the \'include_folders\' and/or \'include_files\' to modify the listing.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createContainer() - Create container and/or add content.',
                'nickname'         => 'createContainer',
                'type'             => 'ContainerResponse',
                'event_name'       => array('{api_name}.{container}.insert', '{api_name}.container_created',),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container you want to put the contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of folders and/or files.',
                        'allowMultiple' => false,
                        'type'          => 'ContainerRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'url',
                        'description'   => 'The full URL of the file to upload.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'extract',
                        'description'   => 'Extract an uploaded zip file into the container.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'clean',
                        'description'   => 'Option when \'extract\' is true, clean the current folder before extracting files and folders.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'check_exist',
                        'description'   => 'If true, the request fails when the file or folder to create already exists.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'X-HTTP-METHOD',
                        'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                        'enum'          => array('GET', 'PUT', 'PATCH', 'DELETE'),
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'header',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data as an array of folders and/or files.',
            ),
            array(
                'method'           => 'PATCH',
                'summary'          => 'updateContainerProperties() - Update properties of the container.',
                'nickname'         => 'updateContainerProperties',
                'type'             => 'Container',
                'event_name'       => array('{api_name}.{container}.update', '{api_name}.container_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container you want to put the contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'An array of container properties.',
                        'allowMultiple' => false,
                        'type'          => 'Container',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data as an array of container properties.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteContainer() - Delete one container and/or its contents.',
                'nickname'         => 'deleteContainer',
                'type'             => 'ContainerResponse',
                'event_name'       => array('{api_name}.{container}.delete', '{api_name}.container_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container you want to delete from.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'force',
                        'description'   => 'Set to true to force delete on a non-empty container.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'content_only',
                        'description'   => 'Set to true to only delete the content of the container.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            =>
                    'Set \'content_only\' to true to delete the folders and files contained, but not the container. ' .
                    'Set \'force\' to true to delete a non-empty container. ' .
                    'Alternatively, to delete by a listing of folders and files, ' .
                    'use the POST request with X-HTTP-METHOD = DELETE header and post listing.',
            ),
        ),
        'description' => 'Operations on containers.',
    ),
    array(
        'path'        => '/{api_name}/{container}/{folder_path}/',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getFolder() - List the folder\'s content, including properties.',
                'nickname'         => 'getFolder',
                'type'             => 'FolderResponse',
                'event_name'       => '{api_name}.{container}.{folder_path}.describe',
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container from which you want to retrieve contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'folder_path',
                        'description'   => 'The path of the folder you want to retrieve. This can be a sub-folder, with each level separated by a \'/\'',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'include_properties',
                        'description'   => 'Return any properties of the folder in the response.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'include_folders',
                        'description'   => 'Include folders in the returned listing.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => true,
                    ),
                    array(
                        'name'          => 'include_files',
                        'description'   => 'Include files in the returned listing.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => true,
                    ),
                    array(
                        'name'          => 'full_tree',
                        'description'   => 'List the contents of all sub-folders as well.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'zip',
                        'description'   => 'Return the content of the folder as a zip file.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            =>
                    'Use \'include_properties\' to get properties of the folder. ' .
                    'Use the \'include_folders\' and/or \'include_files\' to modify the listing.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createFolder() - Create a folder and/or add content.',
                'nickname'         => 'createFolder',
                'type'             => 'FolderResponse',
                'event_name'       => array('{api_name}.{container}.{folder_path}.insert', '{api_name}.{container}.folder_created'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container where you want to put the contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'folder_path',
                        'description'   => 'The path of the folder where you want to put the contents. This can be a sub-folder, with each level separated by a \'/\'',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of folders and/or files.',
                        'allowMultiple' => false,
                        'type'          => 'FolderRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'url',
                        'description'   => 'The full URL of the file to upload.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'extract',
                        'description'   => 'Extract an uploaded zip file into the folder.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'clean',
                        'description'   => 'Option when \'extract\' is true, clean the current folder before extracting files and folders.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'check_exist',
                        'description'   => 'If true, the request fails when the file or folder to create already exists.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'X-HTTP-METHOD',
                        'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                        'enum'          => array('GET', 'PUT', 'PATCH', 'DELETE'),
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'header',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data as an array of folders and/or files. Folders are created if they do not exist',
            ),
            array(
                'method'           => 'PATCH',
                'summary'          => 'updateFolderProperties() - Update folder properties.',
                'nickname'         => 'updateFolderProperties',
                'type'             => 'Folder',
                'event_name'       => array('{api_name}.{container}.{folder_path}.update', '{api_name}.{container}.folder_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container where you want to put the contents.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'folder_path',
                        'description'   => 'The path of the folder you want to update. This can be a sub-folder, with each level separated by a \'/\'',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of folder properties.',
                        'allowMultiple' => false,
                        'type'          => 'Folder',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post body as an array of folder properties.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteFolder() - Delete one folder and/or its contents.',
                'nickname'         => 'deleteFolder',
                'type'             => 'FolderResponse',
                'event_name'       => array('{api_name}.{container}.{folder_path}.delete', '{api_name}.{container}.folder_deleted',),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'The name of the container where the folder exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'folder_path',
                        'description'   => 'The path of the folder where you want to delete contents. This can be a sub-folder, with each level separated by a \'/\'',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'force',
                        'description'   => 'Set to true to force delete on a non-empty folder.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'content_only',
                        'description'   => 'Set to true to only delete the content of the folder.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            =>
                    'Set \'content_only\' to true to delete the sub-folders and files contained, but not the folder. ' .
                    'Set \'force\' to true to delete a non-empty folder. ' .
                    'Alternatively, to delete by a listing of sub-folders and files, ' .
                    'use the POST request with X-HTTP-METHOD = DELETE header and post listing.',
            ),
        ),
        'description' => 'Operations on folders.',
    ),
    array(
        'path'        => '/{api_name}/{container}/{file_path}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getFile() - Download the file contents and/or its properties.',
                'nickname'         => 'getFile',
                'type'             => 'FileResponse',
                'event_name'       => array('{api_name}.{container}.{file_path}.download', '{api_name}.{container}.file_downloaded'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'Name of the container where the file exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'file_path',
                        'description'   => 'Path and name of the file to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'include_properties',
                        'description'   => 'Return properties of the file.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'content',
                        'description'   => 'Return the content as base64 of the file, only applies when \'include_properties\' is true.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                    array(
                        'name'          => 'download',
                        'description'   => 'Prompt the user to download the file from the browser.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'defaultValue'  => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            =>
                    'By default, the file is streamed to the browser. ' .
                    'Use the \'download\' parameter to prompt for download. ' .
                    'Use the \'include_properties\' parameter (optionally add \'content\' to include base64 content) to list properties of the file.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createFile() - Create a new file.',
                'nickname'         => 'createFile',
                'type'             => 'FileResponse',
                'event_name'       => array('{api_name}.{container}.{file_path}.create', '{api_name}.{container}.file_created'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'Name of the container where the file exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'file_path',
                        'description'   => 'Path and name of the file to create.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'check_exist',
                        'description'   => 'If true, the request fails when the file to create already exists.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Content and/or properties of the file.',
                        'allowMultiple' => false,
                        'type'          => 'FileRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post body should be the contents of the file or an object with file properties.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'replaceFile() - Update content of the file.',
                'nickname'         => 'replaceFile',
                'type'             => 'FileResponse',
                'event_name'       => array('{api_name}.{container}.{file_path}.update', '{api_name}.{container}.file_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'Name of the container where the file exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'file_path',
                        'description'   => 'Path and name of the file to update.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'The content of the file.',
                        'allowMultiple' => false,
                        'type'          => 'FileRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post body should be the contents of the file.',
            ),
            array(
                'method'           => 'PATCH',
                'summary'          => 'updateFileProperties() - Update properties of the file.',
                'nickname'         => 'updateFileProperties',
                'type'             => 'File',
                'event_name'       => array('{api_name}.{container}.{file_path}.update', '{api_name}.{container}.file_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'Name of the container where the file exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'file_path',
                        'description'   => 'Path and name of the file to update.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Properties of the file.',
                        'allowMultiple' => false,
                        'type'          => 'File',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post body should be an array of file properties.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteFile() - Delete one file.',
                'nickname'         => 'deleteFile',
                'type'             => 'FileResponse',
                'event_name'       => array('{api_name}.{container}.{file_path}.delete', '{api_name}.{container}.file_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'container',
                        'description'   => 'Name of the container where the file exists.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'file_path',
                        'description'   => 'Path and name of the file to delete.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Careful, this removes the given file from the storage.',
            ),
        ),
        'description' => 'Operations on individual files.',
    ),
);

$_commonContainer = array(
    'name'     => array(
        'type'        => 'string',
        'description' => 'Identifier/Name for the container.',
    ),
    'path'     => array(
        'type'        => 'string',
        'description' => 'Same as name for the container, for consistency.',
    ),
    'metadata' => array(
        'type'        => 'Array',
        'description' => 'An array of name-value pairs.',
        'items'       => array(
            'type' => 'string',
        ),
    ),
);

$_commonFolder = array(
    'name'     => array(
        'type'        => 'string',
        'description' => 'Identifier/Name for the folder, localized to requested resource.',
    ),
    'path'     => array(
        'type'        => 'string',
        'description' => 'Full path of the folder, from the service including container.',
    ),
    'metadata' => array(
        'type'        => 'Array',
        'description' => 'An array of name-value pairs.',
        'items'       => array(
            'type' => 'string',
        ),
    ),
);

$_commonFile = array(
    'name'         => array(
        'type'        => 'string',
        'description' => 'Identifier/Name for the file, localized to requested resource.',
    ),
    'path'         => array(
        'type'        => 'string',
        'description' => 'Full path of the file, from the service including container.',
    ),
    'content_type' => array(
        'type'        => 'string',
        'description' => 'The media type of the content of the file.',
    ),
    'metadata'     => array(
        'type'        => 'Array',
        'description' => 'An array of name-value pairs.',
        'items'       => array(
            'type' => 'string',
        ),
    ),
);

$_models = array(
    'FileRequest'        => array(
        'id'         => 'FileRequest',
        'properties' => $_commonFile,
    ),
    'FileResponse'       => array(
        'id'         => 'FileResponse',
        'properties' => array_merge(
            $_commonFile,
            array(
                'content_length' => array(
                    'type'        => 'string',
                    'description' => 'Size of the file in bytes.',
                ),
                'last_modified'  => array(
                    'type'        => 'string',
                    'description' => 'A GMT date timestamp of when the file was last modified.',
                ),
            )
        ),
    ),
    'FolderRequest'      => array(
        'id'         => 'FolderRequest',
        'properties' => array_merge(
            $_commonFolder,
            array(
                'folder' => array(
                    'type'        => 'Array',
                    'description' => 'An array of sub-folders to create.',
                    'items'       => array(
                        '$ref' => 'FolderRequest',
                    ),
                ),
                'file'   => array(
                    'type'        => 'Array',
                    'description' => 'An array of files to create.',
                    'items'       => array(
                        '$ref' => 'FileRequest',
                    ),
                ),
            )
        ),
    ),
    'FolderResponse'     => array(
        'id'         => 'FolderResponse',
        'properties' => array_merge(
            $_commonFolder,
            array(
                'last_modified' => array(
                    'type'        => 'string',
                    'description' => 'A GMT date timestamp of when the file was last modified.',
                ),
                'folder'        => array(
                    'type'        => 'Array',
                    'description' => 'An array of contained sub-folders.',
                    'items'       => array(
                        '$ref' => 'FolderResponse',
                    ),
                ),
                'file'          => array(
                    'type'        => 'Array',
                    'description' => 'An array of contained files.',
                    'items'       => array(
                        '$ref' => 'FileResponse',
                    ),
                ),
            )
        ),
    ),
    'ContainerRequest'   => array(
        'id'         => 'ContainerRequest',
        'properties' => array_merge(
            $_commonContainer,
            array(
                'folder' => array(
                    'type'        => 'Array',
                    'description' => 'An array of folders to create.',
                    'items'       => array(
                        '$ref' => 'FolderRequest',
                    ),
                ),
                'file'   => array(
                    'type'        => 'Array',
                    'description' => 'An array of files to create.',
                    'items'       => array(
                        '$ref' => 'FileRequest',
                    ),
                ),
            )
        ),
    ),
    'ContainerResponse'  => array(
        'id'         => 'ContainerResponse',
        'properties' => array_merge(
            $_commonContainer,
            array(
                'last_modified' => array(
                    'type'        => 'string',
                    'description' => 'A GMT date timestamp of when the container was last modified.',
                ),
                'folder'        => array(
                    'type'        => 'Array',
                    'description' => 'An array of contained folders.',
                    'items'       => array(
                        '$ref' => 'FolderResponse',
                    ),
                ),
                'file'          => array(
                    'type'        => 'Array',
                    'description' => 'An array of contained files.',
                    'items'       => array(
                        '$ref' => 'FileResponse',
                    ),
                ),
            )
        ),
    ),
    'File'               => array(
        'id'         => 'File',
        'properties' => $_commonFile,
    ),
    'Folder'             => array(
        'id'         => 'Folder',
        'properties' => $_commonFolder,
    ),
    'Container'          => array(
        'id'         => 'Container',
        'properties' => $_commonContainer,
    ),
    'ContainersRequest'  => array(
        'id'         => 'ContainersRequest',
        'properties' => array(
            'container' => array(
                'type'        => 'Array',
                'description' => 'An array of containers to modify.',
                'items'       => array(
                    '$ref' => 'Container',
                ),
            ),
        ),
    ),
    'ContainersResponse' => array(
        'id'         => 'ContainersResponse',
        'properties' => array(
            'container' => array(
                'type'        => 'Array',
                'description' => 'An array of containers.',
                'items'       => array(
                    '$ref' => 'Container',
                ),
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models );

unset( $_commonResponses, $_models, $_commonContainer, $_commonFolder, $_commonFile );

return $_base;

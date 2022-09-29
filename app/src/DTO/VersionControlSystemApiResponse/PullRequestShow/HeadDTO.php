<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestShow;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class HeadDTO
{
	public string $label;
	public string $ref;
	public string $sha;
	public UserDTO $user;
	public RepositoryDTO $repo;
}

<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

class LinksDTO
{
    public HrefDTO $self;
    public HrefDTO $html;
    public HrefDTO $issue;
    public HrefDTO $comments;
    public HrefDTO $review_comments;
    public HrefDTO $review_comment;
    public HrefDTO $commits;
    public HrefDTO $statuses;
}

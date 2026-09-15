<?php
const BACKUP_TABLES=['pages','sectors','projects','translations','media','settings','admins','contact_messages','rate_limits','audit_log'];
function backup_media_path(string $path): string {
    if(preg_match('~^/assets/[a-zA-Z0-9/._-]+\.(png|jpe?g|webp|pdf|mp4)$~D',$path)&&!str_contains($path,'..'))return 'public'.$path;
    if(preg_match('~^[a-f0-9]{40}\.(png|webp|pdf)$~D',$path))return 'storage/uploads/'.$path;
    throw new RuntimeException('Unrecognised media path; backup/restore stopped.');
}

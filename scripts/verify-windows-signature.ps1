param([Parameter(Mandatory=$true)][string]$Path)
$signature = Get-AuthenticodeSignature -FilePath $Path
$hash = Get-FileHash -Path $Path -Algorithm SHA256
[pscustomobject]@{
    File = (Resolve-Path $Path).Path
    SignatureStatus = $signature.Status
    StatusMessage = $signature.StatusMessage
    Publisher = $signature.SignerCertificate.Subject
    Thumbprint = $signature.SignerCertificate.Thumbprint
    SHA256 = $hash.Hash
}
if ($signature.Status -ne 'Valid') { exit 1 }

$miktexPath = "C:\Users\6003625\AppData\Local\Programs\MiKTeX\miktex\bin\x64"
$env:PATH = "$miktexPath;" + $env:PATH
Set-Location "C:\Users\6003625\Documents\00_Proyectos\komorebi\docs"
& "$miktexPath\xelatex.exe" -interaction=nonstopmode documentacion-draft.tex 2>&1 | Out-File compile-out.txt -Encoding utf8
Write-Host "Exit code: $LASTEXITCODE"
Get-Content compile-out.txt | Select-Object -Last 40

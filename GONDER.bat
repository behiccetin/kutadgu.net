@echo off
chcp 65001 >nul
rem =====================================================================
rem  KUTADGU - tek tikla gonder
rem
rem  IKI DEPO VAR VE IKISI AYRI IS GORUR:
rem    origin  (ozel)  behiccetin/kutadgunet  -> sunucu buradan ceker,
rem                    site ~2 dk icinde guncellenir. Tam gecmis burada.
rem    acik    (acik)  behiccetin/kutadgu.net    -> herkesin gordugu kaynak.
rem                    Buraya GECMIS GITMEZ, yalnizca o anki kodun temiz
rem                    bir anlik goruntusu gider (eski commitlerde kisisel
rem                    veriler bulundugu icin).
rem
rem  SIRA BILEREK BOYLE: once acik depo, sonra canli site. Acik depoya
rem  gonderim basarisiz olursa site guncellenmez; boylece site hicbir an
rem  ulasilamayan bir kaynak adresi duyurmaz (AGPL madde 13).
rem =====================================================================
cd /d "%~dp0"
set ACIK=https://github.com/behiccetin/kutadgu.net.git

git add -A
git diff --cached --quiet || git commit -q -m "kutadgu guncelleme %date% %time%"

rem ---- 1. Acik depo: gecmissiz anlik goruntu ----
git remote get-url acik >nul 2>nul || git remote add acik %ACIK%

set TREE=
set PARENT=
set PTREE=
for /f %%t in ('git rev-parse HEAD:') do set TREE=%%t
git fetch -q acik main >nul 2>nul
if not errorlevel 1 (
  for /f %%p in ('git rev-parse FETCH_HEAD') do set PARENT=%%p
  for /f %%q in ('git rev-parse FETCH_HEAD:') do set PTREE=%%q
)

if "%TREE%"=="%PTREE%" (
  echo Acik depo zaten guncel.
  goto canli
)

set YENI=
if defined PARENT (
  for /f %%c in ('git commit-tree %TREE% -p %PARENT% -m "Kutadgu %date%"') do set YENI=%%c
) else (
  for /f %%c in ('git commit-tree %TREE% -m "Kutadgu: ilk acik surum"') do set YENI=%%c
)
if not defined YENI goto hata_acik

git push acik %YENI%:refs/heads/main
if errorlevel 1 goto hata_acik
echo Acik depo guncellendi: %ACIK%

:canli
rem ---- 2. Canli site (ozel depo) ----
git push origin main
if errorlevel 1 (
  echo.
  echo HATA: siteye gonderilemedi. Internet ve GitHub girisini kontrol et.
  pause
  exit /b 1
)
echo.
echo Gonderildi. Yaklasik 2 dakika icinde kutadgu.net guncellenir.
pause
exit /b 0

:hata_acik
echo.
echo ----------------------------------------------------------------
echo  HATA: acik depoya gonderilemedi. Site GUNCELLENMEDI.
echo  Acik depo yoksa once olustur:
echo    github.com/new  -^>  sahibi: behiccetin, adi: kutadgu.net,
echo    BOS ve PUBLIC bir depo ac
echo       (README, lisans ya da .gitignore EKLEME)
echo  Sonra bu dosyayi yeniden calistir.
echo ----------------------------------------------------------------
pause
exit /b 1

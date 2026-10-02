@echo off
chcp 65001 >nul
title Kutadgu - GitHub deposu ilk kurulum
cd /d "%~dp0"

echo.
echo ==========================================================
echo   KUTADGU  -  GitHub deposuna ilk gonderim
echo   Depo: https://github.com/behiccetin/kutadgunet.git
echo ==========================================================
echo.

where git >nul 2>nul
if errorlevel 1 (
  echo HATA: git bulunamadi. Once Git for Windows kurulmali.
  echo https://git-scm.com/download/win
  pause
  exit /b 1
)

if exist ".git" (
  echo Bu klasor zaten bir git deposu. Kurulum atlaniyor.
  echo Guncelleme icin GONDER.bat kullanin.
  pause
  exit /b 0
)

echo [1/6] Depo olusturuluyor...
git init -q
if errorlevel 1 goto hata

echo [2/6] Ana dal main olarak ayarlaniyor...
git branch -M main

echo [3/6] Uzak adres ekleniyor...
git remote add origin https://github.com/behiccetin/kutadgunet.git
if errorlevel 1 goto hata

echo [4/6] Dosyalar ekleniyor...
git add -A
if errorlevel 1 goto hata

echo [5/6] Ilk surum kaydediliyor...
git commit -q -m "Kutadgu ilk surum"
if errorlevel 1 goto hata

echo [6/6] GitHub'a gonderiliyor...
echo (GitHub girisi istenirse tarayici acilir, izin verin)
git push -u origin main
if errorlevel 1 goto hata

echo.
echo ==========================================================
echo   TAMAM. Kod GitHub'da.
echo   Bundan sonra her degisiklik icin GONDER.bat yeter.
echo ==========================================================
echo.
pause
exit /b 0

:hata
echo.
echo ----------------------------------------------------------
echo   HATA olustu. Yukaridaki mesaji Claude'a gosterin.
echo ----------------------------------------------------------
echo.
pause
exit /b 1

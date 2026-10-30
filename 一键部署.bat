@echo off
chcp 65001 >nul
title 任务打卡系统 · 一键部署
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deployemote.ps1" -Mode deploy
echo.
pause

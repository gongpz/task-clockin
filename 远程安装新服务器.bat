@echo off
chcp 65001 >nul
title 任务打卡系统 · 远程安装新服务器
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deployemote.ps1" -Mode install
echo.
pause

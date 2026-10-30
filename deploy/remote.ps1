# 任务打卡系统 · 本机一键部署 / 远程安装（由 一键部署.bat、远程安装新服务器.bat 调用）
# 只用 Windows 自带的 ssh / scp，不需要装别的软件。
param(
  [ValidateSet('deploy', 'install')] [string]$Mode = 'deploy'
)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$Here = Split-Path -Parent $MyInvocation.MyCommand.Path
$TargetsFile = Join-Path $Here 'targets.txt'

function Expand-Key([string]$p) {
  if (-not $p) { return $null }
  if ($p.StartsWith('~')) { $p = Join-Path $HOME $p.Substring(2) }
  return $p
}
function Ssh-Args($t) {
  $a = @('-p', $t.Port, '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ServerAliveInterval=30')
  if ($t.Key) { $a = @('-i', $t.Key) + $a }
  return $a
}

if ($Mode -eq 'deploy') {
  if (-not (Test-Path $TargetsFile)) {
    Copy-Item (Join-Path $Here 'targets.example.txt') $TargetsFile
    Write-Host "已生成 deploy\targets.txt，请先按里面的说明填好服务器，再重新运行。" -ForegroundColor Yellow
    notepad $TargetsFile
    exit 1
  }
  $targets = @()
  foreach ($line in Get-Content $TargetsFile -Encoding UTF8) {
    $l = $line.Trim()
    if (-not $l -or $l.StartsWith('#')) { continue }
    $f = $l.Split('|')
    if ($f.Count -lt 5) { continue }
    $port = $f[2].Trim() -as [int]; if (-not $port) { $port = 22 }
    $targets += [pscustomobject]@{ Name = $f[0].Trim(); Host = $f[1].Trim(); Port = $port; User = $f[3].Trim(); Site = $f[4].Trim(); Key = (Expand-Key ($(if ($f.Count -ge 6) { $f[5].Trim() } else { '' }))) }
  }
  if (-not $targets) { Write-Host "targets.txt 里没有服务器" -ForegroundColor Red; exit 1 }

  Write-Host ""
  Write-Host "========== 任务打卡系统 · 一键部署 ==========" -ForegroundColor Cyan
  for ($i = 0; $i -lt $targets.Count; $i++) { Write-Host ("  {0}. {1}  ({2}@{3}  站点 {4})" -f ($i + 1), $targets[$i].Name, $targets[$i].User, $targets[$i].Host, $targets[$i].Site) }
  Write-Host "  A. 全部"
  $pick = Read-Host "部署到哪里（序号，可用逗号分隔多个）"
  $chosen = if ($pick -match '^[Aa]$') { $targets } else { $pick.Split(',') | ForEach-Object { $n = $_.Trim() -as [int]; if ($n -ge 1 -and $n -le $targets.Count) { $targets[$n - 1] } } }
  if (-not $chosen) { Write-Host "没有选中服务器" -ForegroundColor Red; exit 1 }

  Write-Host "  1. 部署（默认）   2. 回滚到上一次   3. 查看当前版本"
  $act = switch (Read-Host "操作") { '2' { 'rollback' } '3' { 'status' } default { 'deploy' } }
  $ref = ''
  if ($act -eq 'deploy') {
    $ref = Read-Host "分支或提交（直接回车 = 服务器默认分支）"
    if ($ref -and $ref -notmatch '^[A-Za-z0-9._/-]{1,100}$') { Write-Host "分支名不合法" -ForegroundColor Red; exit 1 }
  }
  if ($act -ne 'status' -and ($chosen | Where-Object { $_.Name -notmatch 'DEMO|测试' })) {
    # 名称里不含 DEMO / 测试 的都当正式环境，需要再确认一次
    $yes = Read-Host "包含正式环境，确认$(if ($act -eq 'deploy') { '部署' } else { '回滚' })？输入 yes 继续"
    if ($yes -ne 'yes') { Write-Host "已取消"; exit 0 }
  }

  $fail = 0
  foreach ($t in $chosen) {
    Write-Host ""
    Write-Host "──── $act → $($t.Name) ────" -ForegroundColor Cyan
    $cmd = if ($act -eq 'deploy') { "task-deploy $($t.Site) $ref" } elseif ($act -eq 'rollback') { "task-deploy $($t.Site) --rollback" } else { "task-deploy $($t.Site) --status" }
    & ssh @(Ssh-Args $t) "$($t.User)@$($t.Host)" $cmd
    if ($LASTEXITCODE -ne 0) { $fail = 1; Write-Host "✖ $($t.Name) 失败（看上面的提示；部署失败时服务器已自动回滚代码）" -ForegroundColor Red }
    else { Write-Host "✔ $($t.Name) 完成" -ForegroundColor Green }
  }
  exit $fail
}

# ---------------- 远程安装新服务器 ----------------
Write-Host ""
Write-Host "========== 任务打卡系统 · 远程安装到新服务器 ==========" -ForegroundColor Cyan
Write-Host "要求：一台 Linux 服务器（云服务器或机房服务器都行），你能用 root 通过 SSH 登录。"
$h = Read-Host "服务器地址（IP 或域名）"
if ($h -notmatch '^[A-Za-z0-9.-]+$') { Write-Host "地址不合法" -ForegroundColor Red; exit 1 }
$p = Read-Host "SSH 端口（回车 = 22）"; if (-not $p) { $p = 22 }
$u = Read-Host "登录用户（回车 = root）"; if (-not $u) { $u = 'root' }
$k = Read-Host "SSH 私钥路径（回车 = 用密码登录）"
$t = [pscustomobject]@{ Host = $h; Port = [int]$p; User = $u; Key = (Expand-Key $k) }

Write-Host "上传安装脚本……"
$remoteDir = '/root/task-installer'
& ssh @(Ssh-Args $t) "$u@$h" "mkdir -p $remoteDir"
if ($LASTEXITCODE -ne 0) { Write-Host "连不上服务器" -ForegroundColor Red; exit 1 }
$scpArgs = @('-P', $t.Port, '-o', 'StrictHostKeyChecking=accept-new'); if ($t.Key) { $scpArgs = @('-i', $t.Key) + $scpArgs }
& scp @scpArgs (Join-Path $Here 'install.sh') "${u}@${h}:$remoteDir/"
if ($LASTEXITCODE -ne 0) { Write-Host "上传失败" -ForegroundColor Red; exit 1 }

Write-Host "开始安装（会在服务器上一步步提问）……" -ForegroundColor Cyan
# -t：交互式问答需要终端；sed 去掉 Windows 换行，防止脚本在 Linux 上报错
& ssh -t @(Ssh-Args $t) "$u@$h" "cd $remoteDir && sed -i 's/\r$//' install.sh && bash install.sh"
if ($LASTEXITCODE -ne 0) { Write-Host "安装没有完成，请看上面的提示" -ForegroundColor Red; exit 1 }

$site = Read-Host "安装时填的站点名（用于加入本机部署清单，回车跳过）"
if ($site) {
  $name = Read-Host "给这台服务器起个名字（如 客户A）"
  if (-not (Test-Path $TargetsFile)) { Copy-Item (Join-Path $Here 'targets.example.txt') $TargetsFile }
  Add-Content $TargetsFile -Encoding UTF8 "$name|$h|$p|$u|$site|$k"
  Write-Host "已加入 deploy\targets.txt，以后用「一键部署.bat」选它即可升级。" -ForegroundColor Green
}

; 单聊辅助 - Inno Setup 安装脚本
; 使用 Inno Setup 6 编译

[Setup]
AppName=单聊辅助
AppVersion=1.0
AppPublisher=单聊辅助团队
DefaultDirName={pf}\单聊辅助
DefaultGroupName=单聊辅助
UninstallDisplayIcon={app}\单聊辅助.exe
Compression=lzma2
SolidCompression=yes
OutputDir=.
OutputBaseFilename=单聊辅助_安装程序

[Languages]
Name: "chinesesimplified"; MessagesFile: "compiler:Languages\ChineseSimplified.isl"

[Tasks]
Name: "desktopicon"; Description: "创建桌面快捷方式"; GroupDescription: "快捷方式:"

[Files]
Source: "dist\单聊辅助.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "moban.xlsx"; DestDir: "{app}"; Flags: ignoreversion
Source: "README.txt"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\单聊辅助"; Filename: "{app}\单聊辅助.exe"
Name: "{commondesktop}\单聊辅助"; Filename: "{app}\单聊辅助.exe"; Tasks: desktopicon

[Run]
Filename: "{app}\单聊辅助.exe"; Description: "启动单聊辅助"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "{cmd}"; Parameters: "/c taskkill /f /im 单聊辅助.exe 2>nul"; Flags: runhidden
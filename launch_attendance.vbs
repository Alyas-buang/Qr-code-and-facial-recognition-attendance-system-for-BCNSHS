Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

projectDir = fso.GetParentFolderName(WScript.ScriptFullName)
batchPath = projectDir & "\launch_attendance.bat"

If fso.FileExists(batchPath) Then
    cmd = "cmd /c " & Chr(34) & batchPath & Chr(34)
    exitCode = shell.Run(cmd, 0, True)
    If exitCode <> 0 Then
        MsgBox "Launcher failed. Please run launch_attendance.bat manually to see details.", vbCritical, "Launcher Error"
    End If
Else
    MsgBox "launch_attendance.bat not found in project folder.", vbCritical, "Launcher Error"
End If

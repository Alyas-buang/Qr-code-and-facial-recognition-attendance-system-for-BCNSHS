Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

projectDir = fso.GetParentFolderName(WScript.ScriptFullName)
batchPath = projectDir & "\launch_attendance.bat"

If fso.FileExists(batchPath) Then
    shell.Run Chr(34) & batchPath & Chr(34), 0, False
Else
    MsgBox "launch_attendance.bat not found in project folder.", vbCritical, "Launcher Error"
End If

package app.bitacora

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.activity.enableEdgeToEdge
import app.bitacora.ui.LoginScreen
import app.bitacora.ui.ProjectScreen
import app.bitacora.ui.ProjectsScreen
import app.bitacora.ui.theme.BitacoraTheme

class MainActivity : ComponentActivity() {
    private val vm: AppViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        setContent {
            BitacoraTheme {
                Surface(Modifier.fillMaxSize()) { Root(vm) }
            }
        }
    }
}

@Composable
private fun Root(vm: AppViewModel) {
    when {
        !vm.ready -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator() }
        vm.user == null -> LoginScreen(vm)
        vm.currentProjectId != null -> {
            BackHandler { vm.openProject(null) }
            ProjectScreen(vm)
        }
        else -> ProjectsScreen(vm)
    }
}

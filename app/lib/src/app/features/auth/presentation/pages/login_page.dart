import 'package:phpeste_app/src/app/features/auth/domain/dtos/login_params.dart';
import 'package:phpeste_app/src/app/features/auth/domain/validators/login_params_validators.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/texts/auth_texts.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/utils/show_snack_bar.dart';
import 'package:material_ui/material_ui.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();

  bool _obscurePassword = true;

  LoginParams loginParams = LoginParams.empty();
  final LoginParamsValidators loginParamsValidator = LoginParamsValidators();

  final authbloc = injector<AuthBloc>();

  @override
  void dispose() {
    loginParams = LoginParams.empty();
    _formKey.currentState?.reset();
    _obscurePassword = true;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(AuthTexts.title),
        centerTitle: true,
      ),
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthLoginSuccess) {
            showMessageSnackBar(context, AuthTexts.success, icon: Icons.check_circle, iconColor: Colors.green, textColor: Colors.white);
          }
          if (state is AuthLoginFailure) {
            showMessageSnackBar(context, state.exception.message, icon: Icons.error, iconColor: Colors.red, textColor: Colors.white);
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.person_outline,
                  size: 80,
                  color: Theme.of(context).primaryColor,
                ),
                const SizedBox(height: 32),
                TextFormField(
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: AuthTexts.email,
                    prefixIcon: Icon(Icons.email_outlined),
                    border: OutlineInputBorder(),
                  ),
                  validator: loginParamsValidator.byField(loginParams, 'email'),
                  onChanged: loginParams.setEmail,
                  autovalidateMode: AutovalidateMode.onUserInteraction,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: AuthTexts.password,
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      tooltip: _obscurePassword ? AuthTexts.showPassword : AuthTexts.hidePassword,
                      icon: Icon(
                        _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                    border: const OutlineInputBorder(),
                  ),
                  validator: loginParamsValidator.byField(loginParams, 'password'),
                  onChanged: loginParams.setPassword,
                  autovalidateMode: AutovalidateMode.onUserInteraction,
                ),
                const SizedBox(height: 24),
                BlocBuilder<AuthBloc, AuthState>(
                  builder: (context, state) {
                    return ElevatedButton(
                      onPressed: () {
                        authbloc.add(AuthLoginRequested(params: loginParams));
                      },
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: state is AuthLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                              ),
                            )
                          : const Text(
                              AuthTexts.submit,
                              style: TextStyle(fontSize: 16),
                            ),
                    );
                  },
                ),
                const SizedBox(height: 16),
                TextButton(
                  onPressed: () {},
                  child: const Text(AuthTexts.register),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
